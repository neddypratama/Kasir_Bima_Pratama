<?php

namespace App\Exports;

use App\Models\Stok;
use App\Models\Barang;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class StokPakanExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
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
        return Stok::with(['user', 'barang.jenis', 'barang'])->whereHas('barang.jenis', function ($q) {
                $q->where('name', 'like', 'Pakan%');
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
            'Tanggal',
            'Pembuat',
            'Jenis Barang',
            'Barang',
            'Tambah',
            'Kurang',
            'Kotor',
            'Pecah',
            'Stok Sekarang'
        ];
    }

    /**
     * Atur data per row
     */
    public function map($stok): array
    {
        $rows = [
                $stok->invoice,
                $stok->tanggal,
                $stok->user->name,
                $stok->barang->jenis->name ?? '-',
                $stok->barang?->name ?? '-',
                $stok->tambah ?? 0,
                $stok->kurang ?? 0,
                $stok->kotor ?? 0,
                $stok->rusak ?? 0,
                $stok->barang?->stok
            ];

        return $rows;
    }
}
