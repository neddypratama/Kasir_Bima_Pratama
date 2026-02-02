<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\StokBatch;
use App\Models\Kategori;

class RebuildHppFromSales extends Command
{
    protected $signature = 'hpp:rebuild-from-sales';
    protected $description = 'Rebuild FIFO stok_batch & transaksi HPP dari transaksi penjualan';

    public function handle()
    {
        DB::transaction(function () {

            // =====================
            // RESET FIFO
            // =====================
            $this->info('Reset stok batch...');
            StokBatch::query()->update([
                'qty_sisa' => DB::raw('qty_masuk')
            ]);

            // =====================
            // HAPUS DETAIL HPP
            // =====================
            $this->info('Hapus detail transaksi HPP lama...');
            DetailTransaksi::whereHas('transaksi', function ($q) {
                $q->where('type', 'Debit')->where('invoice', 'like', '%-HPP-%');
            })->delete();

            // =====================
            // HAPUS TRANSAKSI HPP
            // =====================
            $this->info('Hapus transaksi HPP lama...');
            Transaksi::where('type', 'Debit')->where('invoice', 'like', '%-HPP-%')->delete();

            // =====================
            // REPLAY PENJUALAN
            // =====================
            $this->info('Replay transaksi penjualan FIFO...');

            $penjualans = Transaksi::where('type', 'Kredit')->where('invoice', 'like', '%-DPT-%')
                ->orderBy('tanggal')
                ->with('details.barang.jenis')
                ->get();

            foreach ($penjualans as $jual) {

                $totalHpp = 0;
                $hppDetails = [];

                foreach ($jual->details as $detail) {

                    $barang = $detail->barang;
                    $qty = $detail->kuantitas;

                    $batches = StokBatch::where('barang_id', $barang->id)
                        ->where('qty_sisa', '>', 0)
                        ->orderBy('tanggal')
                        ->lockForUpdate()
                        ->get();

                    foreach ($batches as $batch) {
                        if ($qty <= 0) break;

                        $ambil = min($qty, $batch->qty_sisa);

                        $subtotal = $ambil * $batch->harga_beli;
                        $totalHpp += $subtotal;

                        $hppDetails[] = [
                            'barang_id' => $barang->id,
                            'kuantitas' => $ambil,
                            'value'     => $batch->harga_beli,
                            'sub_total' => $subtotal,
                        ];

                        $batch->decrement('qty_sisa', $ambil);
                        $qty -= $ambil;
                    }

                    if ($qty > 0) {
                        throw new \Exception("Stok FIFO {$barang->name} {$jual->invoice} tidak mencukupi");
                    }
                }

                // =====================
                // TRANSAKSI HPP
                // =====================
                $inv = substr($jual->invoice, -4);
                $tanggal = explode('-', $jual->invoice)[1];

                $hpp = Transaksi::create([
                    'invoice'   => 'INV-' . $tanggal . '-HPP-' . $inv,
                    'user_id'   => $jual->user_id,
                    'client_id' => $jual->client_id,
                    'tanggal'   => $jual->tanggal,
                    'type'      => 'Debit',
                    'total'     => $totalHpp,
                    'status'    => $jual->status,
                    'bayar'     => $jual->bayar,
                ]);

                // =====================
                // DETAIL HPP
                // =====================
                foreach ($hppDetails as $d) {
                    $kategori = Kategori::where(
                        'name',
                        'like',
                        'HPP %' . $barang->jenis->name
                    )->first();

                    DetailTransaksi::create([
                        'transaksi_id' => $hpp->id,
                        'barang_id'    => $d['barang_id'],
                        'kategori_id'  => $kategori?->id,
                        'value'        => $d['value'],
                        'kuantitas'    => $d['kuantitas'],
                        'sub_total'    => $d['sub_total'],
                    ]);
                }
            }
        });

        $this->info('Rebuild HPP FIFO selesai & konsisten ✅');
    }
}
