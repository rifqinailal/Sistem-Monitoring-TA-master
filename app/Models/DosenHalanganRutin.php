<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DosenHalanganRutin extends Model
{
    use HasFactory;

    protected $table = 'dosen_halangan_rutins';

    protected $fillable = [
        'dosen_id',
        'hari',
        'sesi_ujian_id',
        'ruangan_id',
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

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }
}
