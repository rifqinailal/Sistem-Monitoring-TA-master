<?php

namespace App\Services\AutoScheduling;

use App\Models\Sidang;
use App\Models\JadwalSeminar;
use App\Models\Dosen;
use App\Models\Ruangan;
use App\Models\SesiUjian;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleService
{
    protected $populationSize = 40;
    protected $maxGeneration  = 50;
    protected $crossoverRate  = 0.8;
    protected $mutationRate   = 0.2;
    protected $maxTabuIter    = 50;
    protected $tabuTenure     = 10;
    protected $penaltyHard    = 1.0;
    protected $penaltySoft    = 0.5;

    protected $dataMahasiswa = [];
    protected $dataDosen = [];
    protected $dataRuangan = [];
    protected $dataSesi = [];
    protected $lockedSlots = [];
    protected $dateList = [];
    protected $modelType;

    // ini fungsi untuk entry point controller
    public function generate($periodeId, $startDate, $endDate, $selectedIds = [], $type = 'sidang')
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $this->pengambilanData($startDate, $endDate, $selectedIds, $type);

        if (empty($this->dataMahasiswa) || empty($this->dateList) || empty($this->dataRuangan)) {
            return ['status' => 'error', 'message' => 'Data tidak lengkap.'];
        }

        $bestSchedule = $this->generateAlgoritma();

        $this->simpanKeDatabase($bestSchedule);

        $totalConflict = count($bestSchedule['fitness_data']['notes']);
        $totalSuccess = count($this->dataMahasiswa) - $totalConflict;
        $score = number_format($bestSchedule['fitness_data']['score'], 4);

        return [
            'status' => 'success',
            'message' => "Selesai! Score: {$score}. Aman: {$totalSuccess}, Bentrok: {$totalConflict}.",
            'details' => $bestSchedule['fitness_data']
        ];
    }

    // ini fungsi untuk pengambilan data
    protected function pengambilanData($start, $end, $selectedIds, $type)
    {
        $this->modelType = $type;
        $curr = Carbon::parse($start);
        $endC = Carbon::parse($end);

        while ($curr->lte($endC)) {
            if (!$curr->isWeekend()) $this->dateList[] = $curr->format('Y-m-d');
            $curr->addDay();
        }

        $this->dataRuangan = Ruangan::pluck('nama_ruangan', 'id')->toArray();

        $urutan = 1;
        foreach (SesiUjian::orderBy('jam_mulai')->get() as $s) {
            $this->dataSesi[$s->id] = ['id' => $s->id, 'nama' => $s->nama, 'order' => $urutan++, 'jam_mulai' => $s->jam_mulai, 'jam_selesai' => $s->jam_selesai];
        }

        foreach (Dosen::with(['halanganRutin', 'halanganTanggal'])->get() as $d) {
            $rutinMap = [];
            foreach ($d->halanganRutin as $hr) $rutinMap[$hr->hari][$hr->sesi_ujian_id] = true;
            $tanggalMap = [];
            foreach ($d->halanganTanggal as $ht) $tanggalMap[$ht->tanggal][$ht->sesi_ujian_id] = true;
            $this->dataDosen[$d->id] = ['nama' => $d->name, 'rutin' => $rutinMap, 'tanggal' => $tanggalMap];
        }

        $modelClass = ($this->modelType == 'sidang') ? Sidang::class : JadwalSeminar::class;
        $fixed = $modelClass::where('status', 'sudah_terjadwal')->whereBetween('tanggal', [$start, $endC->format('Y-m-d')])->get();

        foreach ($fixed as $fix) {
            $this->lockedSlots['room']["{$fix->tanggal}_{$fix->sesi_ujian_id}_{$fix->ruangan_id}"] = true;
            foreach ($this->getDosenIds($fix) as $dId) {
                $this->lockedSlots['dosen']["{$fix->tanggal}_{$fix->sesi_ujian_id}_{$dId}"] = true;
            }
        }

        $candidates = $modelClass::query()->with(['tugas_akhir.bimbingUjis'])->whereIn('id', $selectedIds)->get();
        foreach ($candidates as $row) {
            $this->dataMahasiswa[] = ['id_db' => $row->id, 'dosen_ids' => $this->getDosenIds($row)];
        }
    }

    // ini fungsi untuk inisialisasi populasi
    protected function inisialisasiPopulasi()
    {
        $population = [];
        $sesiIds = array_keys($this->dataSesi);
        $ruangIds = array_keys($this->dataRuangan);

        for ($i = 0; $i < $this->populationSize; $i++) {
            $genes = [];
            foreach ($this->dataMahasiswa as $idx => $mhs) {
                $genes[$idx] = [
                    'id_db' => $mhs['id_db'],
                    'dosen_ids' => $mhs['dosen_ids'],
                    'date' => $this->dateList[array_rand($this->dateList)],
                    'id_sesi' => $sesiIds[array_rand($sesiIds)],
                    'id_ruangan' => $ruangIds[array_rand($ruangIds)]
                ];
            }
            $population[] = ['genes' => $genes, 'fitness_data' => $this->hitungFitness($genes)];
        }

        usort($population, fn($a, $b) => $b['fitness_data']['score'] <=> $a['fitness_data']['score']);
        return $population;
    }

    // ini fungsi untuk hitung fitness
    protected function hitungFitness($genes)
    {
        $totalPenalty = 0;
        $notes = [];
        $localRoom = [];
        $localDosen = [];

        foreach ($genes as $gene) {
            $date = $gene['date'];
            $sesiId = $gene['id_sesi'];
            $ruangId = $gene['id_ruangan'];
            $dayName = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][Carbon::parse($date)->dayOfWeek];

            $geneConflicts = [];
            $isHardConflict = false;

            $keyRoom = "{$date}_{$sesiId}_{$ruangId}";
            if (isset($this->lockedSlots['room'][$keyRoom]) || isset($localRoom[$keyRoom])) {
                $totalPenalty += $this->penaltyHard;
                $geneConflicts[] = "Ruangan Bentrok";
                $isHardConflict = true;
            } else $localRoom[$keyRoom] = true;

            foreach ($gene['dosen_ids'] as $dId) {
                $keyDosen = "{$date}_{$sesiId}_{$dId}";
                if (isset($this->lockedSlots['dosen'][$keyDosen]) || isset($localDosen[$keyDosen])) {
                    $totalPenalty += $this->penaltyHard;
                    $geneConflicts[] = "Dosen jadwal ganda";
                    $isHardConflict = true;
                } else $localDosen[$keyDosen] = true;

                if (isset($this->dataDosen[$dId]['rutin'][$dayName][$sesiId]) || isset($this->dataDosen[$dId]['tanggal'][$date][$sesiId])) {
                    $totalPenalty += $this->penaltyHard;
                    $geneConflicts[] = "Dosen berhalangan";
                    $isHardConflict = true;
                }
            }

            if ($isHardConflict) $notes[$gene['id_db']] = implode('; ', array_unique($geneConflicts));
        }

        return ['score' => 1.0 / (1.0 + $totalPenalty), 'penalty' => $totalPenalty, 'notes' => $notes];
    }

    // ini fungsi untuk seleksi
    protected function seleksi($population)
    {
        $best = null;
        for ($i = 0; $i < 3; $i++) {
            $ind = $population[array_rand($population)];
            if ($best === null || $ind['fitness_data']['score'] > $best['fitness_data']['score']) $best = $ind;
        }
        return $best;
    }

    // ini fungsi untuk crossover
    protected function crossover($genes1, $genes2)
    {
        if (rand(0, 100) / 100 > $this->crossoverRate) return [$genes1, $genes2];
        $point = rand(1, count($genes1) - 1);
        return [
            array_merge(array_slice($genes1, 0, $point), array_slice($genes2, $point)),
            array_merge(array_slice($genes2, 0, $point), array_slice($genes1, $point))
        ];
    }

    // ini fungsi untuk mutasi
    protected function mutasi(&$genes)
    {
        if (rand(0, 100) / 100 > $this->mutationRate) return;
        $idx = array_rand($genes);
        $genes[$idx] = $this->pergerakanTetangga($genes[$idx]);
    }

    // ini fungsi untuk identifikasi konflik
    protected function identifikasiKonflik($individual)
    {
        return array_keys($individual['fitness_data']['notes']);
    }

    // ini fungsi untuk pergerakan tetangga
    protected function pergerakanTetangga($gene)
    {
        $sesiIds = array_keys($this->dataSesi);
        $ruangIds = array_keys($this->dataRuangan);

        if (rand(0, 1)) {
            $gene['id_ruangan'] = $ruangIds[array_rand($ruangIds)];
        } else {
            $gene['date'] = $this->dateList[array_rand($this->dateList)];
            $gene['id_sesi'] = $sesiIds[array_rand($sesiIds)];
            $gene['id_ruangan'] = $ruangIds[array_rand($ruangIds)];
        }
        return $gene;
    }

    // ini fungsi untuk cek tabu list
    protected function cekTabu($moveHash, $tabuList, $currentScore, $bestScore)
    {
        return in_array($moveHash, $tabuList) && $currentScore <= $bestScore;
    }

    // ini fungsi untuk utama menjalankan algoritma
    protected function generateAlgoritma()
    {
        $population = $this->inisialisasiPopulasi();
        $bestSolution = $population[0];

        for ($gen = 1; $gen <= $this->maxGeneration; $gen++) {
            if ($bestSolution['fitness_data']['score'] >= 0.999) break;

            $newPopulation = [];
            $newPopulation[] = $population[0];

            while (count($newPopulation) < $this->populationSize) {
                $p1 = $this->seleksi($population);
                $p2 = $this->seleksi($population);

                [$c1, $c2] = $this->crossover($p1['genes'], $p2['genes']);

                $this->mutasi($c1);
                $this->mutasi($c2);

                $newPopulation[] = ['genes' => $c1, 'fitness_data' => $this->hitungFitness($c1)];
                if (count($newPopulation) < $this->populationSize) {
                    $newPopulation[] = ['genes' => $c2, 'fitness_data' => $this->hitungFitness($c2)];
                }
            }

            usort($newPopulation, fn($a, $b) => $b['fitness_data']['score'] <=> $a['fitness_data']['score']);
            $population = $newPopulation;

            if ($population[0]['fitness_data']['score'] < 0.999) {
                $curr = $population[0];
                $bestLocal = $curr;
                $tabuList = [];

                for ($i = 0; $i < $this->maxTabuIter; $i++) {
                    $conflictIds = $this->identifikasiKonflik($curr);
                    if (empty($conflictIds)) break;

                    $targetId = $conflictIds[array_rand($conflictIds)];
                    $geneIndex = -1;
                    foreach ($curr['genes'] as $k => $g) { if ($g['id_db'] == $targetId) { $geneIndex = $k; break; } }
                    if ($geneIndex === -1) continue;

                    $originalGene = $curr['genes'][$geneIndex];
                    $move = $this->pergerakanTetangga($curr['genes'][$geneIndex]);

                    $curr['genes'][$geneIndex] = $move;
                    $curr['fitness_data'] = $this->hitungFitness($curr['genes']);

                    $moveHash = "{$targetId}_{$move['date']}_{$move['id_sesi']}_{$move['id_ruangan']}";

                    if ($this->cekTabu($moveHash, $tabuList, $curr['fitness_data']['score'], $bestLocal['fitness_data']['score'])) {
                        $curr['genes'][$geneIndex] = $originalGene;
                        continue;
                    }

                    if ($curr['fitness_data']['score'] > $bestLocal['fitness_data']['score']) {
                        $bestLocal = $curr;
                        $tabuList[] = $moveHash;
                        if (count($tabuList) > $this->tabuTenure) array_shift($tabuList);
                    } else {
                        $curr['genes'][$geneIndex] = $originalGene;
                        $curr['fitness_data'] = $bestLocal['fitness_data'];
                    }
                }
                $population[0] = $bestLocal;
            }

            if ($population[0]['fitness_data']['score'] > $bestSolution['fitness_data']['score']) {
                $bestSolution = $population[0];
            }
        }

        return $bestSolution;
    }

    // ini fungsi untuk bantuan ambil id dosen
    protected function getDosenIds($model)
    {
        $dosenIds = [];
        $ta = $model->tugas_akhir;
        if (!$ta || !$ta->bimbingUjis) return [];
        foreach ($ta->bimbingUjis as $bu) {
            if ($this->modelType == 'seminar' && strtolower($bu->jenis) != 'pembimbing') continue;
            $dosenIds[] = $bu->dosen_id;
        }
        return array_unique(array_filter($dosenIds));
    }

    // ini fungsi untuk simpan ke database
    protected function simpanKeDatabase($bestSchedule)
    {
        $modelClass = ($this->modelType == 'sidang') ? Sidang::class : JadwalSeminar::class;
        $notes = $bestSchedule['fitness_data']['notes'];

        DB::beginTransaction();
        try {
            foreach ($bestSchedule['genes'] as $gene) {
                $id = $gene['id_db'];
                $modelClass::where('id', $id)->update([
                    'tanggal'       => $gene['date'],
                    'sesi_ujian_id' => $gene['id_sesi'],
                    'ruangan_id'    => $gene['id_ruangan'],
                    'jam_mulai'     => $this->dataSesi[$gene['id_sesi']]['jam_mulai'],
                    'jam_selesai'   => $this->dataSesi[$gene['id_sesi']]['jam_selesai'],
                    'status'        => isset($notes[$id]) ? 'bentrok' : 'draft',
                    'keterangan'    => isset($notes[$id]) ? $notes[$id] : 'Aman (Auto Generated)',
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Save Error: " . $e->getMessage());
            throw $e;
        }
    }
}
