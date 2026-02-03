<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokBatch extends Model
{
    protected $fillable = [
        'barang_id',
        'user_id',
        'detail_transaksi_id',
        'tanggal',
        'qty_masuk',
        'qty_sisa',
        'harga',
    ];

    public function details()
    {
        return $this->belongsTo(DetailTransaksi::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
