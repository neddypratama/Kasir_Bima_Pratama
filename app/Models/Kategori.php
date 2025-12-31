<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kategori extends Model
{
    protected $table = 'kategoris';

    protected $fillable = [
        'name',
        'deskripsi',
        'laporan_id',
    ];

    public function laporan()
    {
        return $this->belongsTo(Laporan::class);
    }

    public function details()
    {
        return $this->hasMany(DetailTransaksi::class);
    }
}
