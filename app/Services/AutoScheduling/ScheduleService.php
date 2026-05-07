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
    protected $dataRuanganRutin = [];
    protected $modelType;

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
            foreach ($d->halanganRutin as $hr) {
                $sesiId = $hr->sesi_ujian_id ?? 'ALL';
                $rutinMap[$hr->hari][$sesiId] = true;

                if (!empty($hr->ruangan_id)) {
                    $this->dataRuanganRutin[$hr->ruangan_id][$hr->hari][$sesiId] = true;
                }
            }

            $tanggalMap = [];
            foreach ($d->halanganTanggal as $ht) {
                $sesiId = $ht->sesi_ujian_id ?? 'ALL';
                $tanggalMap[$ht->tanggal][$sesiId] = true;
            }
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

    protected function getDosenIds($model)
    {
        $dosenIds = [];
        $ta = $model->tugas_akhir;
        if (!$ta || !$ta->bimbingUjis) return [];
        foreach ($ta->bimbingUjis as $bu) {
            $dosenIds[] = $bu->dosen_id;
        }
        return array_unique(array_filter($dosenIds));
    }

    protected function generateAlgoritma()
    {
        $population = $this->inisialisasiPopulasi();
        $bestSolution = $population[0];

        for ($gen = 1; $gen <= $this->maxGeneration; $gen++) {
            if ($bestSolution['fitness_data']['score'] >= 0.999) break;

            $newPopulation = [];
            $newPopulation[] = $population[0];

            // Siklus Genetic Algorithm (Seleksi, Crossover, Mutasi)
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

            // BAGIAN TABU SEARCH
            if ($population[0]['fitness_data']['score'] < 0.999) {
                // Memanggil fungsi Tabu Search yang sudah diisolasi
                $population[0] = $this->optimasiTabuSearch($population[0]);
            }

            // Update Best Solution
            if ($population[0]['fitness_data']['score'] > $bestSolution['fitness_data']['score']) {
                $bestSolution = $population[0];
            }
        }

        return $bestSolution;
    }

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

    protected function hitungFitness($genes)
    {
        $totalPenalty = 0;
        $notes = [];
        $localRoom = [];
        $localDosen = [];
        $dosenDailySchedule = [];

        foreach ($genes as $gene) {
            $date = $gene['date'];
            $sesiId = $gene['id_sesi'];
            $ruangId = $gene['id_ruangan'];
            $dayName = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][Carbon::parse($date)->dayOfWeek];

            $sesiOrder = $this->dataSesi[$sesiId]['order'];
            $idDb = $gene['id_db'];

            $geneConflicts = [];
            $isHardConflict = false;

            if ($dayName == 'Jumat' && $sesiOrder == 5) {
                $totalPenalty += $this->penaltyHard;
                $geneConflicts[] = "Warning: Jumat Sesi 5";
                $isHardConflict = true;
            }

            $keyRoom = "{$date}_{$sesiId}_{$ruangId}";
            if (isset($this->lockedSlots['room'][$keyRoom]) || isset($localRoom[$keyRoom])) {
                $totalPenalty += $this->penaltyHard;
                $geneConflicts[] = "Ruangan Bentrok (Ganda)";
                $isHardConflict = true;
            } else {
                $localRoom[$keyRoom] = true;
            }

            if (isset($this->dataRuanganRutin[$ruangId][$dayName][$sesiId]) || isset($this->dataRuanganRutin[$ruangId][$dayName]['ALL'])) {
                $totalPenalty += $this->penaltyHard;
                $geneConflicts[] = "Ruangan Dipakai Kuliah Reguler";
                $isHardConflict = true;
            }

            foreach ($gene['dosen_ids'] as $dId) {
                $keyDosen = "{$date}_{$sesiId}_{$dId}";
                if (isset($this->lockedSlots['dosen'][$keyDosen]) || isset($localDosen[$keyDosen])) {
                    $totalPenalty += $this->penaltyHard;
                    $geneConflicts[] = "Dosen jadwal ganda";
                    $isHardConflict = true;
                } else {
                    $localDosen[$keyDosen] = true;
                }

                if (
                    isset($this->dataDosen[$dId]['rutin'][$dayName][$sesiId]) ||
                    isset($this->dataDosen[$dId]['rutin'][$dayName]['ALL']) ||
                    isset($this->dataDosen[$dId]['tanggal'][$date][$sesiId]) ||
                    isset($this->dataDosen[$dId]['tanggal'][$date]['ALL'])
                ) {
                    $totalPenalty += $this->penaltyHard;
                    $geneConflicts[] = "Dosen berhalangan";
                    $isHardConflict = true;
                }

                $dosenDailySchedule[$dId][$date][$sesiOrder] = [
                    'ruangan_id' => $ruangId,
                    'id_db'      => $idDb
                ];
            }

            if ($isHardConflict) {
                $notes[$idDb] = implode('; ', array_unique($geneConflicts));
            }
        }

        foreach ($dosenDailySchedule as $dId => $dates) {
            foreach ($dates as $date => $sessions) {
                ksort($sessions);
                $sessionOrders = array_keys($sessions);

                for ($i = 0; $i < count($sessionOrders) - 1; $i++) {
                    $currOrder = $sessionOrders[$i];
                    $nextOrder = $sessionOrders[$i + 1];

                    if ($nextOrder == $currOrder + 1) {
                        $currRoom = $sessions[$currOrder]['ruangan_id'];
                        $nextRoom = $sessions[$nextOrder]['ruangan_id'];

                        if ($currRoom != $nextRoom) {
                            $totalPenalty += $this->penaltyHard;

                            $currDbId = $sessions[$currOrder]['id_db'];
                            $nextDbId = $sessions[$nextOrder]['id_db'];

                            $notes[$currDbId] = isset($notes[$currDbId]) ? $notes[$currDbId] . "; Dosen Pindah Ruangan" : "Dosen Pindah Ruangan";
                            $notes[$nextDbId] = isset($notes[$nextDbId]) ? $notes[$nextDbId] . "; Dosen Pindah Ruangan" : "Dosen Pindah Ruangan";
                        }
                    }
                }
            }
        }

        return ['score' => 1.0 / (1.0 + $totalPenalty), 'penalty' => $totalPenalty, 'notes' => $notes];
    }

    protected function seleksi($population)
    {
        $topTiga = array_slice($population, 0, 3);
        return $topTiga[array_rand($topTiga)];
    }

    protected function crossover($genes1, $genes2)
    {
        if (rand(0, 100) / 100 > $this->crossoverRate) return [$genes1, $genes2];
        $point = rand(1, count($genes1) - 1);

        $child1 = array_merge(array_slice($genes1, 0, $point), array_slice($genes2, $point));
        $child2 = array_merge(array_slice($genes2, 0, $point), array_slice($genes1, $point));

        return [$child1, $child2];
    }

    protected function mutasi(&$genes)
    {
        if (rand(0, 100) / 100 > $this->mutationRate) return;
        $idx = array_rand($genes);
        $genes[$idx] = $this->pergerakanTetangga($genes[$idx]);
    }



    // Fungsi Tabu Search
    protected function optimasiTabuSearch($individu)
    {
        $curr = $individu;
        $bestLocal = $curr;
        $tabuList = [];

        for ($i = 0; $i < $this->maxTabuIter; $i++) {

            // 1. Identifikasi Konflik
            $conflictIds = $this->identifikasiKonflik($curr);
            if (empty($conflictIds)) break; // Berhenti jika jadwal sudah aman (fitness 1.0)

            // Memilih acak satu jadwal yang bentrok
            $targetId = $conflictIds[array_rand($conflictIds)];
            $geneIndex = -1;
            foreach ($curr['genes'] as $k => $g) {
                if ($g['id_db'] == $targetId) {
                    $geneIndex = $k;
                    break;
                }
            }
            if ($geneIndex === -1) continue;

            $originalGene = $curr['genes'][$geneIndex];

            // 2 & 3. Pencarian Tetangga & Pengecekan Tabu
            $bestNeighData = $this->pencarianTetanggaTerbaik($curr, $targetId, $geneIndex, $originalGene, $tabuList, $bestLocal['fitness_data']['score']);

            // 4. Pembaruan Memori
            $this->pembaruanMemori($bestNeighData, $curr, $bestLocal, $tabuList);
        }

        return $bestLocal;
    }

    // TAHAP 1: Identifikasi Konflik
    protected function identifikasiKonflik($individual)
    {
        // Mengembalikan array ID mahasiswa yang jadwalnya bermasalah
        return array_keys($individual['fitness_data']['notes']);
    }

    // TAHAP 2 & 3: Pencarian Tetangga sekaligus Pengecekan Tabu
    protected function pencarianTetanggaTerbaik($curr, $targetId, $geneIndex, $originalGene, $tabuList, $bestLocalScore)
    {
        $bestNeigh = null;
        $bestMoveHash = null;

        // Mencoba 20 kemungkinan perpindahan
        for ($n = 0; $n < 20; $n++) {
            $move = $this->pergerakanTetangga($originalGene);

            $neighGenes = $curr['genes'];
            $neighGenes[$geneIndex] = $move;
            $neighFitness = $this->hitungFitness($neighGenes);

            $moveHash = "{$targetId}_{$move['date']}_{$move['id_sesi']}_{$move['id_ruangan']}";

            // TAHAP 3: Cek Tabu List (Aspiration Criterion)
            if (!$this->cekTabu($moveHash, $tabuList, $neighFitness['score'], $bestLocalScore)) {
                // Jika tidak Tabu, cari nilai fitness yang tertinggi dari 20 percobaan
                if ($bestNeigh === null || $neighFitness['score'] > $bestNeigh['fitness_data']['score']) {
                    $bestNeigh = [
                        'genes' => $neighGenes,
                        'fitness_data' => $neighFitness
                    ];
                    $bestMoveHash = $moveHash;
                }
            }
        }

        return ['neighbor' => $bestNeigh, 'hash' => $bestMoveHash];
    }

    // Helper untuk mengubah jadwal secara acak (digunakan Mutasi dan TS)
    protected function pergerakanTetangga($gene)
    {
        $sesiIds = array_keys($this->dataSesi);
        $ruangIds = array_keys($this->dataRuangan);
        $sebelum = $gene;

        $gene['date'] = $this->dateList[array_rand($this->dateList)];
        $gene['id_sesi'] = $sesiIds[array_rand($sesiIds)];
        $gene['id_ruangan'] = $ruangIds[array_rand($ruangIds)];

        // dd([
        //     'Gen_Sebelum_Pergerakan' => $sebelum,
        //     'Gen_Sesudah_Pergerakan' => $gene
        // ]);
        return $gene;
    }

    // Helper untuk mengecek Daftar Terlarang
    protected function cekTabu($moveHash, $tabuList, $currentScore, $bestScore)
    {
        return in_array($moveHash, $tabuList) && $currentScore <= $bestScore;
    }
    // protected function cekTabu($moveHash, $tabuList, $currentScore, $bestScore)
    // {
    //     $isTabu = in_array($moveHash, $tabuList);
    //     $hasilFinal = $isTabu && $currentScore <= $bestScore;

    //     if (count($tabuList) >= 1) {
    //         dd([
    //             'Hash_Kandidat_Pergerakan' => $moveHash,
    //             'Isi_Memori_Tabu_List' => $tabuList,
    //             'Apakah_Ada_Di_Tabu_List' => $isTabu ? 'Ya, Terlarang' : 'Tidak',
    //             'Skor_Kandidat_vs_Rekor' => $currentScore . ' vs ' . $bestScore,
    //             'Keputusan_Akhir' => $hasilFinal ? 'DITOLAK (Masuk Tabu)' : 'DITERIMA (Sah / Aspiration)'
    //         ]);
    //     }

    //     return $hasilFinal;
    // }

    // TAHAP 4: Pembaruan Memori & Jadwal
    // Note: Parameter menggunakan "&" (reference) agar variabel asli ikut berubah
    protected function pembaruanMemori($bestNeighData, &$curr, &$bestLocal, &$tabuList)
    {
        if ($bestNeighData['neighbor'] !== null) {
            $curr = $bestNeighData['neighbor']; // Ubah jadwal saat ini jadi jadwal tetangga

            // Jika rekor fitness terpecahkan
            if ($curr['fitness_data']['score'] > $bestLocal['fitness_data']['score']) {
                $bestLocal = $curr;

                // Masukkan hash pergerakan ke Tabu List
                $tabuList[] = $bestNeighData['hash'];

                // Potong antrean Tabu List jika melebihi batas Tenure
                if (count($tabuList) > $this->tabuTenure) {
                    array_shift($tabuList);
                }
            }
        }
    }

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
