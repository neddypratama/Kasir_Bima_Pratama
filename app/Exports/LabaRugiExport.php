<?php

namespace App\Exports;

use App\Models\Transaksi;
use App\Models\Kategori;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LabaRugiExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected string $startDate;
    protected string $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function headings(): array
    {
        return [
            ['Laporan Laba Rugi'],
            ['Periode: ' . Carbon::parse($this->startDate)->format('d M Y') . ' - ' . Carbon::parse($this->endDate)->format('d M Y')],
            [],
            ['Kategori', 'Tipe', 'Total (Rp)'],
        ];
    }

    public function array(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $kategoriPendapatan = Kategori::where('type', 'Pendapatan')->where('name', 'not like' ,'%Truk%')->pluck('name');
        $kategoriPengeluaran = Kategori::where('type', 'Pengeluaran')->where('name', 'not like' ,'%Truk%')->pluck('name');

        // == Pendapatan ==
        $pendapatan = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Pendapatan'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Pendapatan')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(function ($group) {
                $kredit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total');
                $debit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total');
                return $kredit - $debit;
            });

        // == Pengeluaran ==
        $pengeluaran = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Pengeluaran'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Pengeluaran')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(function ($group) {
                $debit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total');
                $kredit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total');
                return $debit - $kredit;
            });

        $bebanPajak = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Pengeluaran')->where('name', 'Beban Pajak'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Pengeluaran' && $d->kategori->name == 'Beban Pajak')
            ->sum('sub_total');

        $pendapatanData = $kategoriPendapatan
            ->mapWithKeys(fn($name) => [$name => $pendapatan[$name] ?? 0])
            ->toArray();

        $pengeluaranData = $kategoriPengeluaran
            ->mapWithKeys(fn($name) => [$name => $pengeluaran[$name] ?? 0])
            ->toArray();

        $totalPendapatan = array_sum($pendapatanData);
        $totalPengeluaran = array_sum($pengeluaranData);
        $labaSebelumPajak = $totalPendapatan - $totalPengeluaran;
        $labaSetelahPajak = $labaSebelumPajak - $bebanPajak;

        $rows = [];

        // Bagian Pendapatan
        $rows[] = ['Pendapatan', '', ''];
        foreach ($pendapatanData as $kategori => $total) {
            $rows[] = [$kategori, 'Pendapatan', $total];
        }
        $rows[] = ['Total Pendapatan', '', $totalPendapatan];
        $rows[] = [];

        // Bagian Pengeluaran
        $rows[] = ['Pengeluaran', '', ''];
        foreach ($pengeluaranData as $kategori => $total) {
            $rows[] = [$kategori, 'Pengeluaran', $total];
        }
        $rows[] = ['Total Pengeluaran', '', $totalPengeluaran];
        $rows[] = [];

        // Ringkasan
        $rows[] = ['Laba Sebelum Pajak', '', $labaSebelumPajak];
        $rows[] = ['Beban Pajak', '', $bebanPajak];
        $rows[] = ['Laba Setelah Pajak', '', $labaSetelahPajak];

        return $rows;
    }

    public function title(): string
    {
        return 'Laporan Laba Rugi';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A4:C4')->getFont()->setBold(true);

        return [
            'A4:C4' => ['font' => ['bold' => true]],
        ];
    }
}
