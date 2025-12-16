<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisBarang extends Model
{
    protected $table = 'jenis_barangs';

    protected $fillable = [
        'name',
        'deskripsi'
    ];

    public function barangs()
    {
        return $this->hasMany(Barang::class);
    }
}
