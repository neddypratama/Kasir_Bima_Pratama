<?php

namespace App\Exports;

use App\Models\Truk;
use App\Models\Client;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

class TrukExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping, WithEvents
{
    protected $clientId;
    protected $startDate;
    protected $endDate;
    protected $client;

    protected $totalPemasukan = 0;
    protected $totalPengeluaran = 0;

    public function __construct($clientId = null, $startDate = null, $endDate = null)
    {
        $this->clientId = $clientId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->client = $clientId ? Client::find($clientId) : null;
    }

    public function collection()
    {
        $query = Truk::with(['client:id,name,alamat,keterangan,bon'])
            ->when($this->clientId, fn($q) => $q->where('client_id', $this->clientId))
            ->when($this->startDate, fn($q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->orderBy('tanggal', 'asc')
            ->get();

        $flattened = new Collection();

        // == MODE REKAP SEMUA CLIENT ====
        if ($this->clientId == 0 || $this->client == null) {
            $grouped = $query->groupBy('client_id');

            foreach ($grouped as $clientId => $transaksis) {
                $client = $transaksis->first()->client;
                $nopol = $client->alamat ?? '-';
                $proyek = $client->name ?? '-';
                $driver = $client->keterangan ?? '-';

                $pemasukanClient = 0;
                $pengeluaranClient = 0;

                foreach ($transaksis as $transaksi) {
                    $pemasukan = 0;
                    $pengeluaran = 0;

                    if ($transaksi->type == 'Kredit') {
                        $pemasukan = $transaksi->total;
                    } else {
                        $pengeluaran = $transaksi->total;
                    }

                    $pemasukanClient += $pemasukan;
                    $pengeluaranClient += $pengeluaran;
                }

                $this->totalPemasukan += $pemasukanClient;
                $this->totalPengeluaran += $pengeluaranClient;

                $flattened->push((object)[
                    'NOPOL' => $nopol,
                    'PROYEK' => $proyek,
                    'DRIVER' => $driver,
                    'pemasukan' => $pemasukanClient,
                    'pengeluaran' => $pengeluaranClient,
                ]);
            }

            return $flattened;
        }

        // == MODE DETAIL PER CLIENT ====
        $pemasukanClient = 0;
        $pengeluaranClient = 0;

        foreach ($query as $transaksi) {
            $pemasukan = 0;
            $pengeluaran = 0;

            if ($transaksi->type == 'Kredit') {
                $pemasukan = $transaksi->total;
            } else {
                $pengeluaran = $transaksi->total;
            }

            $pemasukanClient += $pemasukan;
            $pengeluaranClient += $pengeluaran;

            $this->totalPemasukan += $pemasukan;
            $this->totalPengeluaran += $pengeluaran;

            $flattened->push((object)[
                'tanggal' => $transaksi->tanggal,
                'keterangan' => $transaksi->name,
                'pemasukan' => $pemasukan,
                'pengeluaran' => $pengeluaran,
            ]);
        }

        return $flattened;
    }

    public function headings(): array
    {
        if ($this->clientId == 0 || $this->client == null) {
            return ['NOPOL', 'PROYEK', 'DRIVER', 'Pemasukan', 'Pengeluaran'];
        } else {
            return ['Tanggal', 'Keterangan', 'Pemasukan', 'Pengeluaran'];
        }
    }

    public function map($row): array
    {
        if ($this->clientId == 0 || $this->client == null) {
            return [
                $row->NOPOL,
                $row->PROYEK,
                $row->DRIVER,
                $row->pemasukan ?: '',
                $row->pengeluaran ?: '',
            ];
        }

        return [
            Carbon::parse($row->tanggal)->format('d-M'),
            $row->keterangan,
            $row->pemasukan ?: '',
            $row->pengeluaran ?: '',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                $colEnd = ($this->clientId == 0 || $this->client == null) ? 'E' : 'D';

                // Header laporan
                $sheet->insertNewRowBefore(1, 9);
                $sheet->mergeCells("A1:{$colEnd}1");
                $sheet->setCellValue('A1', 'PT BIMA PRATAMA PERSADA');
                $sheet->mergeCells("A2:{$colEnd}2");
                $sheet->setCellValue('A2', $this->clientId == 0 || $this->client == null
                    ? 'Laporan Laba Rugi Realisasi Proyek (Rekap)'
                    : 'Laporan Laba Rugi Realisasi Proyek (Detail)'
                );

                $periode = 'Periode ' .
                    Carbon::parse($this->startDate)->format('d/m/Y') . ' s/d ' .
                    Carbon::parse($this->endDate)->format('d/m/Y');

                $sheet->mergeCells("A3:{$colEnd}3");
                $sheet->setCellValue('A3', $periode);

                if ($this->clientId != 0 && $this->client) {
                    $sheet->setCellValue('A5', 'PROYEK  : ' . $this->client->name);
                    $sheet->setCellValue('A6', 'NOPOL   : ' . ($this->client->alamat ?? '-'));
                    $sheet->setCellValue('A7', 'DRIVER  : ' . ($this->client->keterangan ?? '-'));
                    $sheet->setCellValue('A8', 'NILAI PEROLEHAN : Rp. ' . number_format($this->client->bon ?? 0, 0, ',', '.'));

                    $highestRow = $sheet->getHighestRow();
                    $totalRow = $highestRow + 2;

                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->setCellValue("C{$totalRow}", $this->totalPemasukan);
                    $sheet->setCellValue("D{$totalRow}", $this->totalPengeluaran);
                } else {
                    $highestRow = $sheet->getHighestRow();
                    $totalRow = $highestRow + 2;

                    $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                    $sheet->setCellValue("D{$totalRow}", $this->totalPemasukan);
                    $sheet->setCellValue("E{$totalRow}", $this->totalPengeluaran);

                    $totalRow += 1;
                    $sheet->setCellValue("C{$totalRow}", 'PENDAPATAN BERSIH');
                    $sheet->setCellValue("E{$totalRow}", ($this->totalPemasukan - $this->totalPengeluaran));
                }

                // Styling
                $sheet->getStyle("A1:{$colEnd}2")->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle("A{$totalRow}:{$colEnd}{$totalRow}")->getFont()->setBold(true);

                // Format angka
                $sheet->getStyle("C8:{$colEnd}{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"Rp" #,##0');
            },
        ];
    }
}
