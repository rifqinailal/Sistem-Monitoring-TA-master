<?php

namespace Database\Seeders;

use App\Models\SesiUjian;
use Illuminate\Database\Seeder;

class SesiUjianSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sesis = [
            ['nama' => 'Sesi 1', 'jam_mulai' => '07:30:00', 'jam_selesai' => '08:20:00'],
            ['nama' => 'Sesi 2', 'jam_mulai' => '08:20:00', 'jam_selesai' => '09:10:00'],
            ['nama' => 'Sesi 3', 'jam_mulai' => '09:10:00', 'jam_selesai' => '10:00:00'],
            ['nama' => 'Sesi 4', 'jam_mulai' => '10:00:00', 'jam_selesai' => '10:50:00'],
            ['nama' => 'Sesi 5', 'jam_mulai' => '10:50:00', 'jam_selesai' => '11:40:00'],
            ['nama' => 'Sesi 6', 'jam_mulai' => '12:30:00', 'jam_selesai' => '13:20:00'],
            ['nama' => 'Sesi 7', 'jam_mulai' => '13:20:00', 'jam_selesai' => '14:10:00'],
            ['nama' => 'Sesi 8', 'jam_mulai' => '14:10:00', 'jam_selesai' => '15:00:00'],
            ['nama' => 'Sesi 9', 'jam_mulai' => '15:00:00', 'jam_selesai' => '15:50:00'],
            ['nama' => 'Sesi 10', 'jam_mulai' => '15:50:00', 'jam_selesai' => '16:20:00'],
        ];

        foreach ($sesis as $sesi) {
            // Menggunakan updateOrCreate untuk mengelakkan duplikasi jika dijalankan semula
            SesiUjian::updateOrCreate(
                ['nama' => $sesi['nama']],
                [
                    'jam_mulai' => $sesi['jam_mulai'],
                    'jam_selesai' => $sesi['jam_selesai'],
                ]
            );
        }
    }
}
