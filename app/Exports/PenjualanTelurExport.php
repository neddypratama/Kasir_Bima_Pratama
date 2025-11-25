<?php

namespace App\Exports;

use App\Models\Transaksi;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Database\Eloquent\Builder;

class PenjualanTelurExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Ambil data transaksi + relasi
     */
    public function collection()
    {
        return Transaksi::with(['client:id,name', 'details.kategori:id,name,type'])
            ->where('type', 'Kredit')
            ->whereHas('details.kategori', function (Builder $q) {
                $q->where('name', 'like', 'Penjualan Telur%');
            })
            ->when($this->startDate, fn($q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->orderBy('tanggal', 'asc')
            ->get();
    }

    /**
     * Atur heading kolom Excel
     */
    public function headings(): array
    {
        return [
            'Invoice',
            'Rincian',
            'Tanggal',
            'Client',
            'Kategori',
            'Barang',
            'Kuantitas',
            'Harga Satuan',
            'Subtotal',
            'Pembuat'
        ];
    }

    /**
     * Atur data per row
     */
    public function map($transaksi): array
    {
        $rows = [];

        // dd($transaksi);

        foreach ($transaksi->details as $detail) {
            $rows[] = [
                $transaksi->invoice,
                $transaksi->name,
                $transaksi->tanggal,
                $transaksi->client?->name ?? '-',
                $detail->kategori?->name ?? '-',
                $detail->barang?->name ?? '-',
                $detail->kuantitas,
                $detail->value ?? 0,
                $detail->kuantitas * ($detail->value ?? 0),
                $transaksi->user->name
            ];
        }

        return $rows;
    }
}
