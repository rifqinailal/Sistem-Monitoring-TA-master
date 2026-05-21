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
        // ==========================================
        // CONFIG: GANTI NAMA FILE DI SINI
        // ==========================================
        $fileName = 'halangan_rutin_genap.csv'; // Ubah jadi 'halangan_rutin_ganjil.csv' jika perlu

        // Kosongkan tabel agar data tidak dobel saat dijalankan ulang
        DB::table('dosen_halangan_rutins')->truncate();

        // ==========================================
        // 1. BACA KAMUS DOSEN
        // ==========================================
        $kamusDosenCsv = [];
        $pathDosenCsv = database_path('seeders/dosen_kamus.csv');
        if (file_exists($pathDosenCsv)) {
            $file = fopen($pathDosenCsv, 'r');
            fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                if (isset($row[0]) && isset($row[1])) {
                    $kamusDosenCsv[trim($row[0])] = trim($row[1]);
                }
            }
            fclose($file);
        }

        // ==========================================
        // 2. BACA KAMUS RUANGAN
        // ==========================================
        $kamusRuanganCsv = [];
        $pathRuanganCsv = database_path('seeders/ruangan_kamus.csv');
        if (file_exists($pathRuanganCsv)) {
            $file = fopen($pathRuanganCsv, 'r');
            fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                if (isset($row[0]) && isset($row[1])) {
                    $kamusRuanganCsv[trim($row[0])] = trim($row[1]);
                }
            }
            fclose($file);
        }

        // ==========================================
        // 3 & 4. AMBIL DATA DB & MAPPING
        // ==========================================
        $dosensDb = Dosen::all();
        $ruangansDb = Ruangan::all();

        $dosenMap = [];
        foreach ($kamusDosenCsv as $csvId => $namaCsv) {
            $match = $dosensDb->first(function ($d) use ($namaCsv) {
                $namaDb = $d->nama_dosen ?? $d->nama ?? $d->name ?? '';
                return stripos($namaDb, $namaCsv) !== false || stripos($namaCsv, $namaDb) !== false;
            });
            if ($match) $dosenMap[$csvId] = $match->id;
        }

        $ruanganMap = [];
        foreach ($kamusRuanganCsv as $csvId => $namaCsv) {
            $match = $ruangansDb->first(function ($r) use ($namaCsv) {
                $namaDb = $r->nama_ruangan ?? $r->nama ?? '';
                return stripos($namaDb, $namaCsv) !== false || stripos($namaCsv, $namaDb) !== false;
            });
            if ($match) $ruanganMap[$csvId] = $match->id;
        }

        // ==========================================
        // 5. BACA FILE UTAMA (FLEKSIBEL)
        // ==========================================
        $pathRutinCsv = database_path('seeders/' . $fileName);
        if (!file_exists($pathRutinCsv)) {
            $this->command->error("File {$fileName} tidak ditemukan!");
            return;
        }

        $dataToInsert = [];
        $file = fopen($pathRutinCsv, 'r');

        // Ambil Header dan bersihkan dari spasi / karakter aneh (BOM) Excel
        $headers = fgetcsv($file);
        $headers[0] = trim($headers[0], "\xEF\xBB\xBF"); // Hapus BOM Mark
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers); // Jadikan huruf kecil semua

        while (($row = fgetcsv($file)) !== false) {
            // Mapping Dinamis: Pasangkan isi data dengan nama Headernya
            $rowData = [];
            foreach ($headers as $index => $headerName) {
                $rowData[$headerName] = isset($row[$index]) ? trim($row[$index]) : null;
            }

            // Ekstrak data berdasarkan kombinasi nama kolom Genap / Ganjil
            $csvDosenId = $rowData['id_dosen'] ?? $rowData['dosen_id'] ?? null;
            $hari       = ucfirst(strtolower($rowData['hari'] ?? ''));
            $sesiId     = $rowData['id_sesi'] ?? $rowData['sesi_ujian_id'] ?? null;
            $keterangan = $rowData['keterangan'] ?? '';

            // Handle ID Ruangan (Jika format Ganjil ada tulisan 'NULL' teks)
            $csvRuanganIdRaw = $rowData['id_ruangan'] ?? $rowData['ruangan_id'] ?? null;
            $csvRuanganId = (strtoupper($csvRuanganIdRaw) === 'NULL' || $csvRuanganIdRaw === '') ? null : $csvRuanganIdRaw;

            // PENCOCOKAN ID SUPER CERDAS:
            // Cek di kamus. Jika tidak ada di kamus, TAPI dia berupa angka (ID langsung dari Sistem Ganjil), pakai angka itu.
            $realDosenId = $dosenMap[$csvDosenId] ?? (is_numeric($csvDosenId) ? $csvDosenId : null);
            $realRuanganId = $csvRuanganId ? ($ruanganMap[$csvRuanganId] ?? (is_numeric($csvRuanganId) ? $csvRuanganId : null)) : null;

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
        // 6. PROSES INSERT
        // ==========================================
        if (count($dataToInsert) > 0) {
            $chunks = array_chunk($dataToInsert, 500);
            foreach ($chunks as $chunk) {
                DB::table('dosen_halangan_rutins')->insert($chunk);
            }
            $this->command->info("SUKSES! " . count($dataToInsert) . " data dari file [{$fileName}] berhasil di-import.");
        } else {
            $this->command->warn("Tidak ada data yang valid untuk dimasukkan dari file [{$fileName}].");
        }
    }
}
