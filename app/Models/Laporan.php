<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    protected $table = 'laporans';

    protected $fillable = [
        'name',
        'deskripsi',
    ];

    public function kategoris()
    {
        return $this->hasMany(Kategori::class);
    }
}
