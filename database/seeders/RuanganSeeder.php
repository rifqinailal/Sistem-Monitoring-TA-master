<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RuanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ruangans = [
            ['kode' => '01', 'nama_ruangan' => 'LAB. HARDWARE', 'lokasi' => 'LAB. HARDWARE'],
            ['kode' => '02', 'nama_ruangan' => 'LAB. COWORKING', 'lokasi' => 'LAB. COWORKING'],
            ['kode' => '03', 'nama_ruangan' => 'LAB. PROGRAM 1', 'lokasi' => 'LAB. PROGRAM 1'],
            ['kode' => '04', 'nama_ruangan' => 'LAB. PROGRAM 2', 'lokasi' => 'LAB. PROGRAM 2'],
            ['kode' => '05', 'nama_ruangan' => 'LAB. BASIS DATA', 'lokasi' => 'LAB. BASIS DATA'],
            ['kode' => '06', 'nama_ruangan' => 'LAB. DESIGN', 'lokasi' => 'LAB. DESIGN'],
            ['kode' => '07', 'nama_ruangan' => 'LAB. MULTIMEDIA', 'lokasi' => 'LAB. MULTIMEDIA'],
            ['kode' => '08', 'nama_ruangan' => 'LAB. TUK', 'lokasi' => 'LAB. TUK'],
            ['kode' => '09', 'nama_ruangan' => 'A301', 'lokasi' => 'A301'],
            ['kode' => '10', 'nama_ruangan' => 'A302', 'lokasi' => 'A302'],
        ];

        foreach ($ruangans as $ruangan) {
            // updateOrCreate mencegah terjadinya duplikasi data saat seeder dijalankan berulang kali
            Ruangan::updateOrCreate(
                ['nama_ruangan' => $ruangan['nama_ruangan']], // Kunci pencarian
                [
                    'kode' => $ruangan['kode'],
                    'lokasi' => $ruangan['lokasi']
                ]
            );
        }
    }
}
