<?php

namespace App\Exports;

use App\Models\Transaksi;
use App\Models\Kategori;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NeracaSaldoExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate = Carbon::parse($endDate)->endOfDay();
    }

    public function array(): array
    {
        // Ambil transaksi dalam rentang tanggal
        $transaksis = Transaksi::with(['details.kategori'])
            ->whereHas('details.kategori', function ($q) {
                $q->where('name', 'not like', '%Truk%');
            })
            ->whereBetween('tanggal', [$this->startDate, $this->endDate])
            ->whereHas('details', fn($q) => $q->where('sub_total', '>', 0))
            ->get();

        // Flatten semua detail ke satu collection
        $details = $transaksis->flatMap(fn($trx) => 
            $trx->details->map(fn($detail) => [
                'kategori' => $detail->kategori?->name,
                'type_kategori' => $detail->kategori?->type,
                'type_transaksi' => strtolower($trx->type), // debit/kredit
                'sub_total' => $detail->sub_total ?? 0,
            ])
        )->filter(fn($d) => $d['kategori']);

        // Group hasil transaksi per kategori
        $grouped = $details->groupBy('kategori')->map(function ($items, $kategori) {
            $first = $items->first();
            $debit = $items->where('type_transaksi', 'debit')->sum('sub_total');
            $kredit = $items->where('type_transaksi', 'kredit')->sum('sub_total');

            return [
                'kategori' => $kategori,
                'type' => $first['type_kategori'],
                'debit' => $debit,
                'kredit' => $kredit,
            ];
        });

        // Ambil semua kategori
        $allKategoris = Kategori::select('name', 'type')->get();

        // Gabungkan kategori tanpa transaksi
        $complete = $allKategoris->map(function ($kategori) use ($grouped) {
            $data = $grouped[$kategori->name] ?? null;
            return [
                'kategori' => $kategori->name,
                'type' => $kategori->type,
                'debit' => $data['debit'] ?? 0,
                'kredit' => $data['kredit'] ?? 0,
            ];
        });

        // Buat array final
        $rows = [];
        $sections = ['Pendapatan', 'Pengeluaran', 'Aset', 'Liabilitas', 'Ekuitas'];

        foreach ($sections as $section) {
            $rows[] = [$section, '', '', '']; // judul bagian
            foreach ($complete->where('type', $section) as $row) {
                $rows[] = [
                    $row['kategori'],
                    $row['type'],
                    $row['debit'] > 0 ? $row['debit'] : 0,
                    $row['kredit'] > 0 ? $row['kredit'] : 0,
                ];
            }
            $rows[] = ['', '', '', '']; // pemisah antar bagian
        }

        // Tambah total di akhir
        $totalDebit = $complete->sum('debit');
        $totalKredit = $complete->sum('kredit');
        $rows[] = ['TOTAL', '', $totalDebit, $totalKredit];

        return $rows;
    }

    public function headings(): array
    {
        return ['Kategori / Akun', 'Tipe', 'Debit', 'Kredit'];
    }

    public function title(): string
    {
        return 'Neraca Saldo';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);

        // Format angka
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("C2:D{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Style untuk total
        $sheet->getStyle("A{$highestRow}:D{$highestRow}")->getFont()->setBold(true);
    }
}
