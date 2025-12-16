<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailTransaksi extends Model
{
    protected $table = 'detail_transaksis';
    protected $fillable = ['transaksi_id', 'value', 'barang_id', 'kuantitas', 'satuan_id', 'sub_total'];
    
    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
    
    public function satuan()
    {
        return $this->belongsTo(KonversiSatuan::class);
    }
}
