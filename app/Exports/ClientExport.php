<?php

namespace App\Exports;

use App\Models\Client;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClientExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct() {

    }
    

    /**
     * Ambil data transaksi + relasi
     */
    public function collection()
    {
        return Client::all();
    }

    /**
     * Atur heading kolom Excel
     */
    public function headings(): array
    {
        return [
            'Nama',
            'Tipe',
            'Alamat',
            'keterangan',
            'Bon',
            'Titipan',
        ];
    }

    /**
     * Atur data per row
     */
    public function map($client): array
    {
            $rows[] = [
                $client->name,
                $client->type,
                $client->alamat,
                $client->keterangan,
                $client->bon ?? 0,
                $client->titipan ?? 0,
            ];
        return $rows;
    }
}
