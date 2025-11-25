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

class AsetExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
            ['Laporan Aset'],
            ['Periode: ' . Carbon::parse($this->startDate)->format('d M Y') . ' - ' . Carbon::parse($this->endDate)->format('d M Y')],
            [],
            ['Kategori', 'Tipe', 'Total (Rp)'],
        ];
    }

    public function array(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $kategoriAset = Kategori::where('type', 'Aset')->pluck('name');
        $kategoriLiabilitas = Kategori::where('type', 'Liabilitas')->pluck('name');

        // == Aset ==
        $Aset = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Aset'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Aset')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(function ($group) {
                $kredit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total');
                $debit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total');
                return $debit - $kredit;
            });

        // == Liabilitas ==
        $Liabilitas = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Liabilitas'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Liabilitas')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(function ($group) {
                $debit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total');
                $kredit = $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total');
                return $kredit - $debit;
            });

        $bebanPajak = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Liabilitas')->where('name', 'Beban Pajak'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Liabilitas' && $d->kategori->name == 'Beban Pajak')
            ->sum('sub_total');

        $AsetData = $kategoriAset
            ->mapWithKeys(fn($name) => [$name => $Aset[$name] ?? 0])
            ->toArray();

        $LiabilitasData = $kategoriLiabilitas
            ->mapWithKeys(fn($name) => [$name => $Liabilitas[$name] ?? 0])
            ->toArray();

        $totalAset = array_sum($AsetData);
        $totalLiabilitas = array_sum($LiabilitasData);

        $rows = [];

        // Bagian Aset
        $rows[] = ['Aset', '', ''];
        foreach ($AsetData as $kategori => $total) {
            $rows[] = [$kategori, 'Aset', $total];
        }
        $rows[] = ['Total Aset', '', $totalAset];
        $rows[] = [];

        // Bagian Liabilitas
        $rows[] = ['Liabilitas', '', ''];
        foreach ($LiabilitasData as $kategori => $total) {
            $rows[] = [$kategori, 'Liabilitas', $total];
        }
        $rows[] = ['Total Liabilitas', '', $totalLiabilitas];
        $rows[] = [];

        return $rows;
    }

    public function title(): string
    {
        return 'Laporan Aset';
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
