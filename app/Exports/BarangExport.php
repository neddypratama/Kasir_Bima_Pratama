<?php

namespace App\Exports;

use App\Models\Barang;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class BarangExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
{

    /**
     * Ambil data transaksi + relasi
     */
    public function collection()
    {
        return Barang::with('jenis')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Atur heading kolom Excel
     */
    public function headings(): array
    {
        return [
            'Nama',
            'Jenis Barang',
            'Stok',
            'HPP',
            'Tanggal Dibuat',
        ];
    }

    /**
     * Atur data per row
     */
    public function map($client): array
    {
            $rows[] = [
                $client->name,
                $client->jenis->name,
                $client->stok ?? 0,
                $client->hpp ?? 0,
                $client->created_at,
            ];
        return $rows;
    }
}
