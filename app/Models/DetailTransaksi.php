<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailTransaksi extends Model
{
    protected $table = 'detail_transaksis';
    protected $fillable = ['transaksi_id', 'kategori_id', 'value', 'barang_id', 'kuantitas', 'sub_total'];
    
    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }
}
