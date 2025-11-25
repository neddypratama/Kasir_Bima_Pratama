<?php

namespace App\Exports;

use App\Models\Stok;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class StokTelurExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Ambil data stok telur sesuai filter
     */
    public function collection()
    {
        return Stok::with(['user', 'barang.jenis', 'barang'])
            ->whereHas('barang.jenis', fn($q) => $q->where('name', 'like', '%Telur%'))
            ->when($this->startDate, fn($q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->orderBy('tanggal', 'asc')
            ->get();
    }

    /**
     * Heading kolom Excel
     */
    public function headings(): array
    {
        return [
            'Invoice',
            'Tanggal',
            'Pembuat',
            'Jenis Barang',
            'Nama Barang',
            'Tambah',
            'Kurang',
            'Kotor',
            'Bentes',
            'Ceplok',
            'Pecah',
            'Stok Sekarang',
        ];
    }

    /**
     * Map data per row
     */
    public function map($stok): array
    {
        return [
            $stok->invoice,
            optional($stok->tanggal)->format('Y-m-d H:i') ?? '-',
            $stok->user?->name ?? '-',
            $stok->barang->jenis?->name ?? '-',
            $stok->barang?->name ?? '-',
            $stok->tambah ?? 0,
            $stok->kurang ?? 0,
            $stok->kotor ?? 0,
            $stok->bentes ?? 0,
            $stok->ceplok ?? 0,
            $stok->rusak ?? 0,
            $stok->barang?->stok ?? 0,
        ];
    }
}
