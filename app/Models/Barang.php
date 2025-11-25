<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'barangs';

    protected $fillable = [
        'name',
        'jenis_id',
        'stok',
        'hpp',
        'harga'
    ];

    public function jenis()
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_id');
    }
}
