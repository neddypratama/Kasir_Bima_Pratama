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
        'satuan',
        'hpp',
        'harga'
    ];

    public function jenis()
    {
        return $this->belongsTo(JenisBarang::class);
    }

    public function details()
    {
        return $this->hasMany(DetailTransaksi::class);
    }
}
