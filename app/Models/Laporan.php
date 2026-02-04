<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    protected $table = 'laporans';

    protected $fillable = [
        'name',
        'deskripsi',
        'type'
    ];

    public function kategoris()
    {
        return $this->hasMany(Kategori::class);
    }
}
