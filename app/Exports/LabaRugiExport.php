<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LabaRugiExport implements
    FromArray,
    WithHeadings,
    WithTitle,
    ShouldAutoSize,
    WithStyles
{
    protected string $startDate;
    protected string $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    /* ======================
        HEADER
    ====================== */
    public function headings(): array
    {
        return [
            ['LAPORAN LABA RUGI'],
            [
                'Periode: ' .
                Carbon::parse($this->startDate)->format('d M Y') .
                ' - ' .
                Carbon::parse($this->endDate)->format('d M Y')
            ],
            [],
            ['Kategori', 'Tipe', 'Total (Rp)'],
        ];
    }

    /* ======================
        DATA
    ====================== */
    public function array(): array
    {
        $rows = [];

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end   = Carbon::parse($this->endDate)->endOfDay();

        /**
         * 1️⃣ AMBIL SEMUA KATEGORI LAPORAN (DEFAULT 0)
         */
        $kategori = DB::table('kategoris as k')
            ->join('laporans as l', 'l.id', '=', 'k.laporan_id')
            ->whereIn('l.type', ['Pendapatan', 'Pengeluaran'])
            ->select(
                'k.id',
                'k.name as kategori',
                'l.name as laporan',
                'l.type'
            )
            ->get();

        $pendapatan = [];
        $pengeluaran = [];

        foreach ($kategori as $k) {
            if ($k->type === 'Pendapatan') {
                $pendapatan[$k->kategori] = 0;
            } else {
                $pengeluaran[$k->kategori] = 0;
            }
        }

        /**
         * 2️⃣ AMBIL TRANSAKSI (YANG ADA SAJA)
         */
        $transaksi = DB::table('detail_transaksis as dt')
            ->join('kategoris as k', 'k.id', '=', 'dt.kategori_id')
            ->join('laporans as l', 'l.id', '=', 'k.laporan_id')
            ->join('transaksis as t', 't.id', '=', 'dt.transaksi_id')
            ->where('t.status', 'Lunas')
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('k.name', 'l.type')
            ->select(
                'k.name as kategori',
                'l.type',
                DB::raw('SUM(dt.sub_total) as total')
            )
            ->get();

        /**
         * 3️⃣ ISI NILAI SESUAI KATEGORI
         */
        foreach ($transaksi as $row) {
            if ($row->type === 'Pendapatan') {
                $pendapatan[$row->kategori] = $row->total;
            } elseif ($row->type === 'Pengeluaran') {
                $pengeluaran[$row->kategori] = $row->total;
            } 
        }

        /**
         * ===== OUTPUT =====
         */

        /* ===== PENDAPATAN ===== */
        $rows[] = ['Pendapatan', '', ''];
        foreach ($pendapatan as $nama => $total) {
            $rows[] = [$nama, 'Pendapatan', $total ?? 0];
        }
        $totalPendapatan = array_sum($pendapatan);
        $rows[] = ['Total Pendapatan', '', $totalPendapatan];
        $rows[] = [];

        /* ===== PENGELUARAN ===== */
        $rows[] = ['Pengeluaran', '', ''];
        foreach ($pengeluaran as $nama => $total) {
            $rows[] = [$nama, 'Pengeluaran', $total ?? 0];
        }
        $totalPengeluaran = array_sum($pengeluaran);
        $rows[] = ['Total Pengeluaran', '', $totalPengeluaran];
        $rows[] = [];

        /* ===== LABA / RUGI ===== */
        $rows[] = ['Laba / Rugi', '', $totalPendapatan - $totalPengeluaran];

        return $rows;
    }

    public function title(): string
    {
        return 'Laba Rugi';
    }

    /* ======================
        STYLING
    ====================== */
    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');

        $sheet->getStyle('A1')->getFont()
            ->setBold(true)
            ->setSize(14);

        $sheet->getStyle('A2')->getFont()
            ->setItalic(true);

        $sheet->getStyle('A4:C4')->getFont()
            ->setBold(true);

        return [];
    }
}
