<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jadwal_seminars', function (Blueprint $table) {
            $table->foreignId('sesi_ujian_id')->nullable()->after('ruangan_id')->references('id')->on('sesi_ujians')->onDelete('set null');
            $table->text('keterangan')->nullable()->after('status');
        });

        DB::statement("ALTER TABLE jadwal_seminars MODIFY COLUMN status ENUM('belum_terjadwal','sudah_terjadwal','telah_seminar','draft','bentrok') NOT NULL DEFAULT 'belum_terjadwal'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_seminars', function (Blueprint $table) {
            $table->dropForeign(['sesi_ujian_id']);
            $table->dropColumn('sesi_ujian_id');
            $table->dropColumn('keterangan');
        });

        DB::statement("ALTER TABLE jadwal_seminars MODIFY COLUMN status ENUM('belum_terjadwal','sudah_terjadwal','telah_seminar') NOT NULL DEFAULT 'belum_terjadwal'");
    }
};
