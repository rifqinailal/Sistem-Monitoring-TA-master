<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SesiUjian extends Model
{
    use HasFactory;

    protected $table = 'sesi_ujians';

    protected $primaryKey = 'id';

    protected $fillable = [
        'nama',
        'jam_mulai',
        'jam_selesai',
        'is_active',
    ];
}
