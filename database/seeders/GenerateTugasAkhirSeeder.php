<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Mahasiswa;
use App\Models\Dosen;
use App\Models\TugasAkhir;
use App\Models\BimbingUji;
use App\Models\JadwalSeminar;
use App\Models\PeriodeTa;
use App\Models\JenisTa;
use App\Models\Topik;
use Carbon\Carbon;

class GenerateTugasAkhirSeeder extends Seeder
{
    public function run(): void
    {
        // ==============================================================================
        // 1. KOSONGKAN DATA LAMA (DISABLE FOREIGN KEY)
        // ==============================================================================
        Schema::disableForeignKeyConstraints();

        DB::table('penilaians')->truncate();
        DB::table('revisis')->truncate();
        DB::table('pemberkasans')->truncate();
        DB::table('sidangs')->truncate();
        DB::table('jadwal_seminars')->truncate();
        DB::table('bimbing_ujis')->truncate();
        DB::table('tugas_akhirs')->truncate();

        Schema::enableForeignKeyConstraints();

        // ==============================================================================
        // 2. SIAPKAN REFERENSI
        // ==============================================================================
        $periode = PeriodeTa::where('is_active', 1)->first() ?? PeriodeTa::create([
            'nama' => 'Periode Genap Auto Scheduling',
            'mulai_seminar' => Carbon::now()->subDays(2),
            'akhir_seminar' => Carbon::now()->addDays(30),
            'is_active' => 1,
        ]);

        $jenisTa = JenisTa::firstOrCreate(['nama_jenis' => 'Skripsi / Proyek Akhir']);
        $topik = Topik::firstOrCreate(['nama_topik' => 'Pengembangan Sistem Informasi']);

        $dosens = Dosen::pluck('id')->toArray();

        if (count($dosens) < 4) {
            $this->command->error('Error: Data Dosen minimal 4!');
            return;
        }

        // ==============================================================================
        // 3. AMBIL DATA MAHASISWA & KELOMPOKKAN BERDASARKAN PRODI
        // ==============================================================================
        // Ini akan mencegah mahasiswa beda prodi masuk ke kelompok yang sama
        $mahasiswasByProdi = Mahasiswa::orderBy('kelas')
                                      ->orderBy('nim')
                                      ->get()
                                      ->groupBy('program_studi_id');

        if ($mahasiswasByProdi->isEmpty()) {
            $this->command->error('Error: Data Mahasiswa kosong!');
            return;
        }

        $bebanDosen = array_fill_keys($dosens, 0);
        $projects = [];

        $maxKelompok = 30; // Target total kelompok
        $hitungKelompok = 0;

        // ==============================================================================
        // 4. PEMBAGIAN KELOMPOK & INDIVIDU (PER PRODI)
        // ==============================================================================
        foreach ($mahasiswasByProdi as $prodiId => $mahasiswas) {
            // Reset index array per prodi agar perulangan while berjalan normal
            $mhsProdi = $mahasiswas->values()->toArray();
            $i = 0;

            while ($i < count($mhsProdi)) {
                // Pastikan masih butuh kelompok DAN masih ada sisa 1 teman di prodi yang sama
                if ($hitungKelompok < $maxKelompok && ($i + 1) < count($mhsProdi)) {
                    $projects[] = [
                        'is_group' => true,
                        'members'  => [$mhsProdi[$i]['id'], $mhsProdi[$i+1]['id']],
                        'judul'    => 'Pengembangan Sistem Skala Besar (TA Kelompok ' . ($hitungKelompok + 1) . ')',
                    ];
                    $i += 2; // Lompat 2 orang
                    $hitungKelompok++;
                } else {
                    $projects[] = [
                        'is_group' => false,
                        'members'  => [$mhsProdi[$i]['id']],
                        'judul'    => 'Rancang Bangun Aplikasi Mobile ' . $mhsProdi[$i]['nama_mhs'],
                    ];
                    $i++; // Lompat 1 orang
                }
            }
        }

        // ==============================================================================
        // 5. PEMBAGIAN DOSEN & INSERT DATA
        // ==============================================================================
        DB::beginTransaction();
        try {
            $totalTa = 0;

            foreach ($projects as $proj) {
                asort($bebanDosen);
                $kandidatDosen = array_slice(array_keys($bebanDosen), 0, 4);
                shuffle($kandidatDosen);

                $bebanTambahan = count($proj['members']);
                foreach ($kandidatDosen as $dId) {
                    $bebanDosen[$dId] += $bebanTambahan;
                }

                foreach ($proj['members'] as $mhsId) {
                    $ta = TugasAkhir::create([
                        'jenis_ta_id'   => $jenisTa->id,
                        'topik_id'      => $topik->id,
                        'mahasiswa_id'  => $mhsId,
                        'periode_ta_id' => $periode->id,
                        'judul'         => $proj['judul'],
                        'tipe'          => $proj['is_group'] ? 'K' : 'I',
                        'status'        => 'acc',
                        'is_completed'  => 1,
                        'status_pemberkasan' => 'belum_lengkap', // Sesuai ENUM di database
                    ]);

                    $roles = [
                        ['dosen_id' => $kandidatDosen[0], 'jenis' => 'pembimbing', 'urut' => 1],
                        ['dosen_id' => $kandidatDosen[1], 'jenis' => 'pembimbing', 'urut' => 2],
                        ['dosen_id' => $kandidatDosen[2], 'jenis' => 'penguji', 'urut' => 1],
                        ['dosen_id' => $kandidatDosen[3], 'jenis' => 'penguji', 'urut' => 2],
                    ];

                    foreach ($roles as $role) {
                        BimbingUji::create([
                            'tugas_akhir_id' => $ta->id,
                            'dosen_id'       => $role['dosen_id'],
                            'jenis'          => $role['jenis'],
                            'urut'           => $role['urut'],
                        ]);
                    }

                    JadwalSeminar::create([
                        'tugas_akhir_id' => $ta->id,
                        'status'         => 'belum_terjadwal', // Anggap sudah selesai seminar
                    ]);

                    // ==========================================
                    // KODE TAMBAHAN UNTUK TESTING SIDANG
                    // ==========================================
                    // \App\Models\Sidang::create([
                    //     'tugas_akhir_id' => $ta->id,
                    //     'status'         => 'sudah_daftar', // Akan muncul di Tab Belum Daftar (Belum Terjadwal)
                    // ]);

                    $totalTa++;
                }
            }

            DB::commit();
            $this->command->info("Berhasil! $totalTa Tugas Akhir dibuat dan dikelompokkan sesuai Prodi.");
            $this->command->info("Terdapat: $hitungKelompok Kelompok & " . (count($projects) - $hitungKelompok) . " Individu.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Gagal: ' . $e->getMessage());
        }
    }
}
