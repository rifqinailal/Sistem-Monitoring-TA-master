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
        Schema::table('sidangs', function (Blueprint $table) {
            $table->foreignId('sesi_ujian_id')->nullable()->after('ruangan_id')->references('id')->on('sesi_ujians')->onDelete('set null');
            $table->text('keterangan')->nullable()->after('status');
        });

        DB::statement("ALTER TABLE sidangs MODIFY COLUMN status ENUM('belum_daftar','sudah_daftar','draft','bentrok','sudah_terjadwal','sudah_sidang') NOT NULL DEFAULT 'belum_daftar'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sidangs', function (Blueprint $table) {
            $table->dropForeign(['sesi_ujian_id']);
            $table->dropColumn('sesi_ujian_id');
            $table->dropColumn('keterangan');
        });

        DB::statement("ALTER TABLE sidangs MODIFY COLUMN status ENUM('belum_daftar','sudah_daftar','sudah_terjadwal','sudah_sidang') NOT NULL DEFAULT 'belum_daftar'");
    }
};
