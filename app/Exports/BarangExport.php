<?php

namespace App\Exports;

use App\Models\Barang;
use App\Models\DetailTransaksi;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class BarangExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
{
    /**
     * Ambil data barang + stok sisa
     */
    public function collection()
    {
        return Barang::query()
            ->with('jenis')
            ->withSum('stokBatches as stok_fifo', 'qty_sisa')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Header Excel
     */
    public function headings(): array
    {
        return [
            'Nama Barang',
            'Jenis Barang',
            'Stok (FIFO)',
            'HPP Terbaru',
            'Harga Eceran',
            'Harga Partai',
            'Tanggal Dibuat',
        ];
    }

    /**
     * Mapping per baris
     */
    public function map($barang): array
    {
        // 🔥 Ambil HPP TERBARU dari transaksi stok
        $hppTerbaru = DetailTransaksi::query()
            ->where('barang_id', $barang->id)
            ->whereHas('transaksi', function ($q) {
                $q->where('type', 'Stok');
            })
            ->join('transaksis as t', 't.id', '=', 'detail_transaksis.transaksi_id')
            ->orderByDesc('t.tanggal')
            ->orderByDesc('detail_transaksis.id')
            ->value('detail_transaksis.value');

        return [
            $barang->name,
            $barang->jenis?->name ?? '-',
            (int) ($barang->stok_fifo ?? 0),
            (float) ($hppTerbaru ?? 0),
            (float) ($barang->harga_eceran ?? 0),
            (float) ($barang->harga_sak ?? 0),
            optional($barang->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
