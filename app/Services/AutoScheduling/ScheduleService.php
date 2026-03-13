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
    // --- PARAMETER ALGORITMA (SESUAI REQUEST) ---
    protected $populationSize = 40;    // Ukuran populasi
    protected $maxGeneration = 50;     // Jumlah generasi
    protected $crossoverRate = 0.8;    // Peluang kawin silang
    protected $mutationRate = 0.2;     // Peluang mutasi

    // Konfigurasi Tabu Search dalam Generasi
    protected $maxTabuIter = 10;       // Iterasi Tabu per individu (Kecil saja karena dijalankan sering)

    // Konfigurasi Penalti (Sesuai Request)
    protected $penaltyHard = 1.0;      // Nilai Hard Constraint
    protected $penaltySoft = 0.5;      // Nilai Soft Constraint

    // --- MEMORY CACHE ---
    protected $dataMahasiswa = [];
    protected $dataDosen = [];
    protected $dataRuangan = [];
    protected $dataSesi = [];
    protected $lockedSlots = [];
    protected $dateList = [];
    protected $modelType;

    /**
     * Entry Point
     */
    public function generate($periodeId, $startDate, $endDate, $selectedIds = [], $type = 'sidang')
    {
        set_time_limit(300); // 5 Menit Time Limit
        ini_set('memory_limit', '512M');

        $this->modelType = $type;

        // 1. Persiapan Data
        $this->initializeData($startDate, $endDate, $selectedIds);

        // 2. Validasi
        if (empty($this->dataMahasiswa)) {
            return ['status' => 'error', 'message' => 'Tidak ada data mahasiswa yang dipilih.'];
        }
        if (empty($this->dateList) || empty($this->dataRuangan) || empty($this->dataSesi)) {
            return ['status' => 'error', 'message' => 'Data referensi (Tanggal/Ruangan/Sesi) tidak lengkap.'];
        }

        // 3. Eksekusi Algoritma (Memetic: GA + Tabu tiap generasi)
        $bestSchedule = $this->runMemeticAlgorithm();

        // 4. Simpan
        $this->saveToDatabase($bestSchedule);

        $totalConflict = count($bestSchedule['fitness_data']['notes']);
        $totalSuccess = count($this->dataMahasiswa) - $totalConflict;
        $score = number_format($bestSchedule['fitness_data']['score'], 4);

        return [
            'status' => 'success',
            'message' => "Selesai! Score: {$score}. Aman: {$totalSuccess}, Bentrok: {$totalConflict}.",
            'details' => $bestSchedule['fitness_data']
        ];
    }

    /**
     * Inisialisasi Data (Sama seperti sebelumnya)
     */
    protected function initializeData($start, $end, $selectedIds)
    {
        // A. Generate Tanggal
        $curr = Carbon::parse($start);
        $endC = Carbon::parse($end);
        $this->dateList = [];
        while ($curr->lte($endC)) {
            if (!$curr->isWeekend()) {
                $this->dateList[] = $curr->format('Y-m-d');
            }
            $curr->addDay();
        }

        // B. Load Ruangan
        $this->dataRuangan = Ruangan::pluck('nama_ruangan', 'id')->toArray();

        // C. Load Sesi & Urutan
        $sesis = SesiUjian::orderBy('jam_mulai')->get();
        $urutan = 1;
        foreach ($sesis as $s) {
            $this->dataSesi[$s->id] = [
                'id' => $s->id,
                'nama' => $s->nama,
                'order' => $urutan++,
                'jam_mulai' => $s->jam_mulai,     // <--- Tambahkan ini
                'jam_selesai' => $s->jam_selesai
            ];
        }

        // D. Load Dosen & Halangan
        $dosens = Dosen::with(['halanganRutin', 'halanganTanggal'])->get();
        foreach ($dosens as $d) {
            $rutinMap = [];
            foreach ($d->halanganRutin as $hr) {
                $rutinMap[$hr->hari][$hr->sesi_ujian_id] = true;
            }
            $tanggalMap = [];
            foreach ($d->halanganTanggal as $ht) {
                $tanggalMap[$ht->tanggal][$ht->sesi_ujian_id] = true;
            }
            $this->dataDosen[$d->id] = ['nama' => $d->name, 'rutin' => $rutinMap, 'tanggal' => $tanggalMap];
        }

        // E. Load Locked Slots
        $modelClass = ($this->modelType == 'sidang') ? Sidang::class : JadwalSeminar::class;
        $fixedSchedules = $modelClass::where('status', 'sudah_terjadwal')
            ->whereBetween('tanggal', [$start, $endC->format('Y-m-d')])->get();

        foreach ($fixedSchedules as $fix) {
            $this->lockedSlots['room']["{$fix->tanggal}_{$fix->sesi_ujian_id}_{$fix->ruangan_id}"] = true;
            foreach ($this->getDosenIdsFromModel($fix) as $dId) {
                $this->lockedSlots['dosen']["{$fix->tanggal}_{$fix->sesi_ujian_id}_{$dId}"] = true;
            }
        }

        // F. Load Mahasiswa
        $query = $modelClass::query()->with(['tugas_akhir.bimbingUjis'])->whereIn('id', $selectedIds);
        $candidates = $query->get();
        $this->dataMahasiswa = [];
        foreach ($candidates as $row) {
            $this->dataMahasiswa[] = [
                'id_db' => $row->id,
                'dosen_ids' => $this->getDosenIdsFromModel($row),
            ];
        }
    }

    protected function getDosenIdsFromModel($model)
    {
        $dosenIds = [];
        $ta = $model->tugas_akhir;
        if (!$ta || !$ta->bimbingUjis) return [];

        foreach ($ta->bimbingUjis as $bu) {
            // Jika penjadwalan seminar, biasanya hanya pembimbing yang dicek jadwalnya (tergantung aturan kampus)
            // Jika penjadwalan sidang, pembimbing dan penguji wajib dicek jadwalnya
            if ($this->modelType == 'seminar') {
                if (strtolower($bu->jenis) == 'pembimbing') {
                    $dosenIds[] = $bu->dosen_id;
                }
            } else {
                // Untuk sidang, masukkan semua id dosen (pembimbing & penguji)
                $dosenIds[] = $bu->dosen_id;
            }
        }
        return array_unique(array_filter($dosenIds));
    }

    /**
     * LOGIKA UTAMA: MEMETIC ALGORITHM
     * GA + Tabu Search setiap generasi pada anak (offspring)
     */
    protected function runMemeticAlgorithm()
    {
        // 1. Populasi Awal
        $population = [];
        for ($i = 0; $i < $this->populationSize; $i++) {
            $genes = $this->createRandomGenes();
            // Tidak perlu TS di inisialisasi agar variasi tinggi
            $population[] = ['genes' => $genes, 'fitness_data' => $this->calculateFitness($genes)];
        }

        // Sort Populasi Awal
        usort($population, fn($a, $b) => $b['fitness_data']['score'] <=> $a['fitness_data']['score']);
        $bestSolution = $population[0];

        // 2. Loop Generasi
        for ($gen = 1; $gen <= $this->maxGeneration; $gen++) {

            // Cek kondisi berhenti sempurna (Penalty 0 -> Score 1.0)
            if ($bestSolution['fitness_data']['score'] >= 0.999) break;

            $newPopulation = [];

            // Elitism: Bawa 1 individu terbaik langsung ke generasi depan (tanpa diubah)
            $newPopulation[] = $population[0];

            // Isi sisa populasi
            while (count($newPopulation) < $this->populationSize) {
                // A. Selection
                $parent1 = $this->tournamentSelection($population);
                $parent2 = $this->tournamentSelection($population);

                // B. Crossover
                [$genes1, $genes2] = $this->crossover($parent1['genes'], $parent2['genes']);

                // C. Mutation
                $this->mutation($genes1);
                $this->mutation($genes2);

                // D. MEMETIC STEP: Apply Tabu Search pada Anak!
                // Anak langsung "belajar" memperbaiki diri sebelum masuk populasi
                $child1 = $this->runTabuSearch(['genes' => $genes1, 'fitness_data' => $this->calculateFitness($genes1)]);
                $child2 = $this->runTabuSearch(['genes' => $genes2, 'fitness_data' => $this->calculateFitness($genes2)]);

                $newPopulation[] = $child1;
                // Cek overflow populasi
                if (count($newPopulation) < $this->populationSize) {
                    $newPopulation[] = $child2;
                }
            }

            // Replace & Sort Populasi
            $population = $newPopulation;
            usort($population, fn($a, $b) => $b['fitness_data']['score'] <=> $a['fitness_data']['score']);

            // Update Best Global Solution
            if ($population[0]['fitness_data']['score'] > $bestSolution['fitness_data']['score']) {
                $bestSolution = $population[0];
            }
        }

        return $bestSolution;
    }

    protected function createRandomGenes()
    {
        $genes = [];
        $sesiIds = array_keys($this->dataSesi);
        $ruangIds = array_keys($this->dataRuangan);

        foreach ($this->dataMahasiswa as $idx => $mhs) {
            $genes[$idx] = [
                'id_db' => $mhs['id_db'],
                'dosen_ids' => $mhs['dosen_ids'],
                'date' => $this->dateList[array_rand($this->dateList)],
                'id_sesi' => $sesiIds[array_rand($sesiIds)],
                'id_ruangan' => $ruangIds[array_rand($ruangIds)]
            ];
        }
        return $genes;
    }

    /**
     * Hitung Fitness dengan Rumus 1 / (1 + TotalPenalty)
     * Hard = 1, Soft = 0.5
     */
    protected function calculateFitness($genes)
    {
        $totalPenalty = 0;
        $notes = [];

        $localRoomUsage = [];
        $localDosenUsage = [];
        $dosenSchedule = [];

        foreach ($genes as $key => $gene) {
            $date = $gene['date'];
            $sesiId = $gene['id_sesi'];
            $ruangId = $gene['id_ruangan'];
            $dayName = $this->getDayName($date);

            $geneConflicts = [];
            $isHardConflict = false;

            // --- HARD CONSTRAINTS (Penalty = 1.0) ---

            // 1. Bentrok Ruangan
            $keyRoom = "{$date}_{$sesiId}_{$ruangId}";
            if (isset($this->lockedSlots['room'][$keyRoom]) || isset($localRoomUsage[$keyRoom])) {
                $totalPenalty += $this->penaltyHard;
                $geneConflicts[] = "Ruangan Bentrok";
                $isHardConflict = true;
            } else {
                $localRoomUsage[$keyRoom] = true;
            }

            // 2. Cek Dosen (Waktu, Halangan Rutin, Halangan Tanggal)
            foreach ($gene['dosen_ids'] as $dId) {
                // Simpan jadwal untuk cek soft constraint nanti
                $dosenSchedule[$dId][$date][$this->dataSesi[$sesiId]['order']] = $ruangId;

                $dosenName = $this->dataDosen[$dId]['nama'] ?? 'Dosen';
                $keyDosen = "{$date}_{$sesiId}_{$dId}";

                // Dosen Menguji 2 Tempat
                if (isset($this->lockedSlots['dosen'][$keyDosen]) || isset($localDosenUsage[$keyDosen])) {
                    $totalPenalty += $this->penaltyHard;
                    $geneConflicts[] = "$dosenName jadwal ganda";
                    $isHardConflict = true;
                } else {
                    $localDosenUsage[$keyDosen] = true;
                }

                // Halangan Rutin
                if (isset($this->dataDosen[$dId]['rutin'][$dayName][$sesiId])) {
                    $totalPenalty += $this->penaltyHard;
                    $geneConflicts[] = "$dosenName halangan rutin";
                    $isHardConflict = true;
                }

                // Halangan Tanggal
                if (isset($this->dataDosen[$dId]['tanggal'][$date][$sesiId])) {
                    $totalPenalty += $this->penaltyHard;
                    $geneConflicts[] = "$dosenName ijin tanggal ini";
                    $isHardConflict = true;
                }
            }

            if ($isHardConflict) {
                $notes[$gene['id_db']] = implode('; ', array_unique($geneConflicts));
            }
        }

        // --- SOFT CONSTRAINTS (Penalty = 0.5) ---
        // Dosen Pindah Ruangan di Sesi Berurutan
        foreach ($dosenSchedule as $dId => $dates) {
            foreach ($dates as $date => $orders) {
                ksort($orders);
                $prevOrder = -99;
                $prevRuang = null;
                foreach ($orders as $order => $rId) {
                    if ($order == $prevOrder + 1) { // Berurutan
                        if ($prevRuang != $rId) {
                            // Pelanggaran Soft
                            $totalPenalty += $this->penaltySoft;
                            // Opsional: Catat di notes tapi jangan dianggap 'bentrok' fatal
                        }
                    }
                    $prevOrder = $order;
                    $prevRuang = $rId;
                }
            }
        }

        // RUMUS FITNESS: 1 / (1 + Penalty)
        // Jika Penalty 0 -> Score 1
        // Jika Penalty 0.5 -> Score 0.66
        // Jika Penalty 1 -> Score 0.5
        $score = 1.0 / (1.0 + $totalPenalty);

        return ['score' => $score, 'penalty' => $totalPenalty, 'notes' => $notes];
    }

    protected function tournamentSelection($population)
    {
        $k = 3;
        $best = null;
        for ($i = 0; $i < $k; $i++) {
            $ind = $population[array_rand($population)];
            if ($best === null || $ind['fitness_data']['score'] > $best['fitness_data']['score']) {
                $best = $ind;
            }
        }
        return $best;
    }

    protected function crossover($genes1, $genes2)
    {
        if (rand(0, 100) / 100 > $this->crossoverRate) return [$genes1, $genes2];

        $point = rand(1, count($genes1) - 1);
        return [
            array_merge(array_slice($genes1, 0, $point), array_slice($genes2, $point)),
            array_merge(array_slice($genes2, 0, $point), array_slice($genes1, $point))
        ];
    }

    protected function mutation(&$genes)
    {
        if (rand(0, 100) / 100 > $this->mutationRate) return;

        $idx = array_rand($genes);
        $sesiIds = array_keys($this->dataSesi);
        $ruangIds = array_keys($this->dataRuangan);

        $genes[$idx]['date'] = $this->dateList[array_rand($this->dateList)];
        $genes[$idx]['id_sesi'] = $sesiIds[array_rand($sesiIds)];
        $genes[$idx]['id_ruangan'] = $ruangIds[array_rand($ruangIds)];
    }

    /**
     * Tabu Search (Dijalankan setiap Individu per Generasi)
     */
    protected function runTabuSearch($individual)
    {
        $bestLocal = $individual;
        $curr = $individual;

        // Ambil daftar gen yang menyebabkan konflik (penalti > 0)
        // Kita prioritaskan memperbaiki yang Hard Conflict dulu
        $conflictIds = array_keys($curr['fitness_data']['notes']);

        // Jika tidak ada konflik, return (atau bisa lanjut optimasi soft constraint)
        if (empty($conflictIds)) return $bestLocal;

        $sesiIds = array_keys($this->dataSesi);
        $ruangIds = array_keys($this->dataRuangan);

        // Limit iterasi kecil karena dijalankan di dalam loop GA
        for ($i = 0; $i < $this->maxTabuIter; $i++) {
            $targetId = $conflictIds[array_rand($conflictIds)];

            // Cari index gen
            $idx = -1;
            foreach ($curr['genes'] as $k => $g) {
                if ($g['id_db'] == $targetId) {
                    $idx = $k;
                    break;
                }
            }
            if ($idx == -1) continue;

            // Generate Tetangga (Move)
            $moveType = rand(1, 3);
            if ($moveType == 1) $curr['genes'][$idx]['date'] = $this->dateList[array_rand($this->dateList)];
            elseif ($moveType == 2) $curr['genes'][$idx]['id_sesi'] = $sesiIds[array_rand($sesiIds)];
            else $curr['genes'][$idx]['id_ruangan'] = $ruangIds[array_rand($ruangIds)];

            // Hitung Fitness Tetangga
            $curr['fitness_data'] = $this->calculateFitness($curr['genes']);

            // Jika lebih baik, simpan
            if ($curr['fitness_data']['score'] > $bestLocal['fitness_data']['score']) {
                $bestLocal = $curr;
                // Update konflik list
                $conflictIds = array_keys($curr['fitness_data']['notes']);
                if (empty($conflictIds)) break; // Jika sudah bersih, stop
            }
        }

        return $bestLocal;
    }

    protected function saveToDatabase($bestSchedule)
    {
        $modelClass = ($this->modelType == 'sidang') ? Sidang::class : JadwalSeminar::class;
        $notes = $bestSchedule['fitness_data']['notes'];

        DB::beginTransaction();
        try {
            foreach ($bestSchedule['genes'] as $gene) {
                $id = $gene['id_db'];
                // Jika ID ada di notes, berarti Hard Conflict
                if (isset($notes[$id])) {
                    $status = 'bentrok';
                    $ket = $notes[$id];
                } else {
                    $status = 'draft';
                    // Cek nilai penalti akhir, jika > 0 tapi tidak ada di notes,
                    // berarti terkena Soft Constraint (misal: pindah ruangan)
                    // Kita bisa beri info "Aman (Optimasi)"
                    $ket = 'Aman (Auto Generated)';
                }

                $modelClass::where('id', $id)->update([
                    'tanggal'       => $gene['date'],
                    'sesi_ujian_id' => $gene['id_sesi'],
                    'ruangan_id'    => $gene['id_ruangan'],
                    'jam_mulai'     => $this->dataSesi[$gene['id_sesi']]['jam_mulai'],    // <--- Isi ini
                    'jam_selesai'   => $this->dataSesi[$gene['id_sesi']]['jam_selesai'],  // <--- Isi ini
                    'status'        => $status,
                    'keterangan'    => $ket,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Save Error: " . $e->getMessage());
            throw $e;
        }
    }

    protected function getDayName($date)
    {
        $dt = Carbon::parse($date);
        $map = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
        return $map[$dt->dayOfWeek];
    }
}
