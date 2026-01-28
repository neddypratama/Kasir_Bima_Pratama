<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stok extends Model
{
    protected $fillable = [
        'invoice',
        'user_id',
        'tanggal',
        'barang_id',
        'tambah',
        'kurang',
        'kotor',
        'rusak'
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
