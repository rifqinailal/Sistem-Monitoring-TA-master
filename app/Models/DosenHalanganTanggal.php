<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DosenHalanganTanggal extends Model
{
    use HasFactory;

    protected $table = 'dosen_halangan_tanggals';

    protected $fillable = [
        'dosen_id',
        'tanggal',
        'sesi_ujian_id',
        'keterangan',
    ];


    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'dosen_id');
    }

    public function sesi()
    {
        return $this->belongsTo(SesiUjian::class, 'sesi_ujian_id');
    }
}
