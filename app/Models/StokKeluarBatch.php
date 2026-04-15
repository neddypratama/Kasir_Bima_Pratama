<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokKeluarBatch extends Model
{
    protected $fillable = [
        'detail_transaksi_id',
        'stok_batch_id',
        'returned_qty',
        'qty',
        'harga',
    ];

    public function detailTransaksi()
    {
        return $this->belongsTo(DetailTransaksi::class);
    }

    public function stokBatch()
    {
        return $this->belongsTo(StokBatch::class);
    }
}
