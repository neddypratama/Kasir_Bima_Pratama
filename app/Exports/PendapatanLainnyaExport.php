<?php

namespace App\Exports;

use App\Models\Transaksi;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class PendapatanLainnyaExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
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
                $q->where('name', 'not like', '%Telur%')->where('name', 'not like', '%Pakan%')->where('name', 'not like', '%Obat-Obatan%')->where('name', 'not like', '%EggTray%')->where('type', 'Pendapatan');
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
            'Kategori',
            'Total',
            'Pembuat'
        ];
    }

    /**
     * Atur data per row
     */
    public function map($transaksi): array
    {
        $rows = [];

        foreach ($transaksi->details as $detail) {
            $rows[] = [
                $transaksi->invoice,
                $transaksi->name,
                $transaksi->tanggal,
                $detail->kategori?->name ?? '-',
                $transaksi->total,
                $transaksi->user->name,
        ];
        }

        return $rows;
    }
}
