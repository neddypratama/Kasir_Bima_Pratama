<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\StokBatch;
use App\Models\Kategori;
use App\Models\Barang;

class RebuildHppFromSales extends Command
{
    protected $signature = 'hpp:rebuild-from-sales';
    protected $description = 'Rebuild FIFO stok_batch & update transaksi HPP dari penjualan';

    public function handle()
    {
        DB::transaction(function () {

            // =====================
            // RESET FIFO
            // =====================
            $this->info('Reset stok batch FIFO...');
            StokBatch::query()->update([
                'qty_sisa' => DB::raw('qty_masuk')
            ]);

            // =====================
            // AMBIL PENJUALAN
            // =====================
            $this->info('Replay transaksi penjualan...');
            $penjualans = Transaksi::where('type', 'Kredit')
                ->where('invoice', 'like', '%-DPT-%')
                ->orderBy('tanggal')
                ->with('details.barang.jenis')
                ->get();

            foreach ($penjualans as $jual) {

                $totalHpp = 0;
                $hppDetails = [];

                // =====================
                // FIFO HITUNG ULANG
                // =====================
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

                        $subtotal = $ambil * $batch->harga;
                        $totalHpp += $subtotal;

                        $hppDetails[] = [
                            'barang_id' => $barang->id,
                            'jenis'     => $barang->jenis->name,
                            'kuantitas' => $ambil,
                            'value'     => $batch->harga,
                            'sub_total' => $subtotal,
                        ];

                        $batch->decrement('qty_sisa', $ambil);
                        $qty -= $ambil;
                    }

                    if ($qty > 0) {
                        throw new \Exception("FIFO stok {$barang->name} tidak cukup ({$jual->invoice})");
                    }
                }

                // =====================
                // TRANSAKSI HPP (UPDATE / CREATE)
                // =====================
                $invSuffix = substr($jual->invoice, -4);
                $tanggalInv = explode('-', $jual->invoice)[1];
                $hppInvoice = 'INV-' . $tanggalInv . '-HPP-' . $invSuffix;

                $hpp = Transaksi::where('invoice', $hppInvoice)
                    ->lockForUpdate()
                    ->first();

                if (!$hpp) {
                    $hpp = Transaksi::create([
                        'invoice'   => $hppInvoice,
                        'user_id'   => $jual->user_id,
                        'client_id' => $jual->client_id,
                        'tanggal'   => $jual->tanggal,
                        'type'      => 'Debit',
                        'total'     => 0,
                        'status'    => $jual->status,
                        'bayar'     => $jual->bayar,
                    ]);
                }

                // update total
                $hpp->update([
                    'total' => $totalHpp
                ]);

                // =====================
                // DETAIL HPP (RESET PER TRANSAKSI)
                // =====================
                DetailTransaksi::where('transaksi_id', $hpp->id)->delete();

                foreach ($hppDetails as $d) {

                    $kategori = Kategori::where(
                        'name',
                        'like',
                        'HPP %' . $d['jenis']
                    )->firstOrFail();

                    DetailTransaksi::create([
                        'transaksi_id' => $hpp->id,
                        'barang_id'    => $d['barang_id'],
                        'kategori_id'  => $kategori->id,
                        'value'        => $d['value'],
                        'kuantitas'    => $d['kuantitas'],
                        'sub_total'    => $d['sub_total'],
                    ]);
                }
            }
        });

        $this->info('Rebuild HPP FIFO selesai & AMAN ✅');
    }
}
