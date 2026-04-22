<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Dosen;
use App\Models\Ruangan;
use Carbon\Carbon;

class HalanganRutinSeeder extends Seeder
{
    public function run(): void
    {
        // Kosongkan tabel agar data tidak dobel saat dijalankan ulang
        DB::table('dosen_halangan_rutins')->truncate();

        // ==========================================
        // 1. BACA KAMUS DOSEN
        // ==========================================
        $kamusDosenCsv = [];
        $pathDosenCsv = database_path('seeders/dosen_kamus.csv');
        if (file_exists($pathDosenCsv)) {
            $file = fopen($pathDosenCsv, 'r');
            fgetcsv($file); // Lewati baris header
            while (($row = fgetcsv($file)) !== false) {
                if (isset($row[0]) && isset($row[1])) {
                    $kamusDosenCsv[trim($row[0])] = trim($row[1]); // [id_csv => nama_dosen]
                }
            }
            fclose($file);
        } else {
            $this->command->error("File dosen_kamus.csv tidak ditemukan!");
            return;
        }

        // ==========================================
        // 2. BACA KAMUS RUANGAN
        // ==========================================
        $kamusRuanganCsv = [];
        $pathRuanganCsv = database_path('seeders/ruangan_kamus.csv');
        if (file_exists($pathRuanganCsv)) {
            $file = fopen($pathRuanganCsv, 'r');
            fgetcsv($file); // Lewati baris header
            while (($row = fgetcsv($file)) !== false) {
                if (isset($row[0]) && isset($row[1])) {
                    $kamusRuanganCsv[trim($row[0])] = trim($row[1]); // [id_csv => nama_ruangan]
                }
            }
            fclose($file);
        }

        // ==========================================
        // 3. AMBIL DATA ASLI DARI DATABASE
        // ==========================================
        $dosensDb = Dosen::all();
        $ruangansDb = Ruangan::all();

        // ==========================================
        // 4. PENCOCOKAN (MAPPING) NAMA KE ID DATABASE
        // ==========================================
        $dosenMap = [];
        foreach ($kamusDosenCsv as $csvId => $namaCsv) {
            $match = $dosensDb->first(function ($d) use ($namaCsv) {
                // Menyesuaikan kolom nama di DB (bisa nama_dosen, nama, atau name)
                $namaDb = $d->nama_dosen ?? $d->nama ?? $d->name ?? '';
                return stripos($namaDb, $namaCsv) !== false || stripos($namaCsv, $namaDb) !== false;
            });
            if ($match) {
                $dosenMap[$csvId] = $match->id;
            }
        }

        $ruanganMap = [];
        foreach ($kamusRuanganCsv as $csvId => $namaCsv) {
            $match = $ruangansDb->first(function ($r) use ($namaCsv) {
                $namaDb = $r->nama_ruangan ?? $r->nama ?? '';
                return stripos($namaDb, $namaCsv) !== false || stripos($namaCsv, $namaDb) !== false;
            });
            if ($match) {
                $ruanganMap[$csvId] = $match->id;
            }
        }

        // ==========================================
        // 5. BACA FILE HALANGAN RUTIN & INSERT
        // ==========================================
        $pathRutinCsv = database_path('seeders/halangan_rutin.csv');
        if (!file_exists($pathRutinCsv)) {
            $this->command->error("File halangan_rutin.csv tidak ditemukan!");
            return;
        }

        $dataToInsert = [];
        $file = fopen($pathRutinCsv, 'r');
        fgetcsv($file); // Lewati Header

        while (($row = fgetcsv($file)) !== false) {
            // Format CSV: id_dosen, hari, id_sesi, keterangan, id_ruangan
            if (count($row) < 4) continue;

            $csvDosenId = trim($row[0]);
            $hari = ucfirst(strtolower(trim($row[1]))); // Pastikan format Hari sesuai ENUM
            $sesiId = trim($row[2]);
            $keterangan = trim($row[3]);
            $csvRuanganId = isset($row[4]) && trim($row[4]) !== '' ? trim($row[4]) : null;

            // Dapatkan ID asli dari hasil mapping
            $realDosenId = $dosenMap[$csvDosenId] ?? null;
            $realRuanganId = $csvRuanganId ? ($ruanganMap[$csvRuanganId] ?? null) : null;

            // Jika dosen asli ditemukan dan sesi valid
            if ($realDosenId && is_numeric($sesiId)) {
                $dataToInsert[] = [
                    'dosen_id'      => $realDosenId,
                    'hari'          => $hari,
                    'sesi_ujian_id' => $sesiId,
                    'ruangan_id'    => $realRuanganId,
                    'keterangan'    => $keterangan,
                    'created_at'    => Carbon::now(),
                    'updated_at'    => Carbon::now(),
                ];
            }
        }
        fclose($file);

        // ==========================================
        // 6. PROSES INSERT (CHUNK)
        // ==========================================
        if (count($dataToInsert) > 0) {
            $chunks = array_chunk($dataToInsert, 500); // Eksekusi per 500 baris agar tidak berat
            foreach ($chunks as $chunk) {
                DB::table('dosen_halangan_rutins')->insert($chunk);
            }
            $this->command->info('SUKSES! ' . count($dataToInsert) . ' data Halangan Rutin berhasil di-import dan dicocokkan.');
        } else {
            $this->command->warn('Tidak ada data yang cocok untuk dimasukkan.');
        }
    }
}
