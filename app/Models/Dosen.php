<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\DosenHalanganRutin;
use App\Models\DosenHalanganTanggal;

class Dosen extends Model
{
    use HasFactory;
    protected $table = 'dosens';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }

    public function programStudi()
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function halanganRutin()
    {
        return $this->hasMany(DosenHalanganRutin::class, 'dosen_id', 'id');
    }

    public function halanganTanggal()
    {
        return $this->hasMany(DosenHalanganTanggal::class, 'dosen_id', 'id');
    }
}
