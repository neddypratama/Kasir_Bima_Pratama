<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KonversiSatuan extends Model
{
    protected $table = 'konversi_satuans';

    protected $fillable = [
        'name',
        'barang_id',
        'konversi',
        'harga',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}
