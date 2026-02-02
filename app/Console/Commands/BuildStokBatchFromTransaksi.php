<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use App\Models\StokBatch;
use App\Models\DetailTransaksi;

class BuildStokBatchFromTransaksi extends Command
{
    protected $signature = 'stok:build-batch';
    protected $description = 'Generate stok_batches dari transaksi pembelian (type = Stok)';

    public function handle()
    {
        $this->info('Mulai membangun stok batch...');

        $transaksis = Transaksi::where('type', 'Stok')
            ->orderBy('tanggal')
            ->with('details')
            ->get();

        foreach ($transaksis as $trx) {
            foreach ($trx->details as $detail) {

                // Cegah double insert
                $exists = StokBatch::where('detail_transaksi_id', $detail->id)->exists();
                if ($exists) {
                    continue;
                }

                StokBatch::create([
                    'barang_id' => $detail->barang_id,
                    'detail_transaksi_id' => $detail->id,
                    'qty_masuk' => $detail->kuantitas,
                    'qty_sisa' => $detail->kuantitas,
                    'harga' => $detail->value,
                    'tanggal' => $trx->tanggal,
                ]);
            }
        }

        $this->info('Stok batch berhasil dibuat.');
    }
}
