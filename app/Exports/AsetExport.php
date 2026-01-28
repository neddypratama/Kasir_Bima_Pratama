<?php

namespace App\Exports;

use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AsetExport implements
    FromArray,
    WithHeadings,
    WithTitle,
    ShouldAutoSize,
    WithStyles
{
    protected ?string $startDate;
    protected ?string $endDate;

    public function __construct($startDate = null, $endDate = null)
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
            ['LAPORAN ASET'],
            ['Periode: ' .
                ($this->startDate
                    ? Carbon::parse($this->startDate)->format('d M Y')
                    : '-') .
                ' - ' .
                ($this->endDate
                    ? Carbon::parse($this->endDate)->format('d M Y')
                    : '-')],
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

        $first = Transaksi::orderBy('tanggal')->first();
        $last  = Transaksi::orderByDesc('tanggal')->first();

        if (!$first || !$last) {
            return [];
        }

        $start = $this->startDate
            ? Carbon::parse($this->startDate)->startOfDay()
            : Carbon::parse($first->tanggal)->startOfDay();

        $end = $this->endDate
            ? Carbon::parse($this->endDate)->endOfDay()
            : Carbon::parse($last->tanggal)->endOfDay();


        $data = DB::table('detail_transaksis as dt')
            ->join('kategoris as k', 'k.id', '=', 'dt.kategori_id')
            ->join('laporans as l', 'l.id', '=', 'k.laporan_id')
            ->join('transaksis as t', 't.id', '=', 'dt.transaksi_id')
            ->select(
                'l.name as laporan',
                'l.type',
                'k.name as kategori',
                't.type as transaksi_type',
                't.status',
                DB::raw('SUM(dt.sub_total) as total')
            )
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy(
                'l.name',
                'l.type',
                'k.name',
                't.type',
                't.status'
            )
            ->get();

        $aset = [];
        $liabilitas = [];

        foreach ($data as $row) {
            $laporan  = $row->laporan;
            $kategori = $row->kategori;
            $nilai    = (float) $row->total;

            /* ===== ASET ===== */
            if (
                ($row->type === 'Pendapatan' &&
                    $row->transaksi_type === 'Kredit' &&
                    $row->status === 'Hutang')
                ||
                ($row->type === 'Aset' &&
                    $row->transaksi_type === 'Stok' &&
                    $row->status === 'Lunas')
            ) {
                if (Str::startsWith($laporan, 'Penjualan ')) {
                    $laporan = 'Bon ' . Str::after($laporan, 'Penjualan ');
                }

                $aset[$laporan] = ($aset[$laporan] ?? 0) + $nilai;
            }

            /* ===== LIABILITAS ===== */
            if (
                $row->type === 'Aset' &&
                $row->transaksi_type === 'Stok' &&
                $row->status === 'Hutang'
            ) {
                if (Str::startsWith($laporan, 'Stok ')) {
                    $laporan = 'Hutang ' . Str::after($laporan, 'Stok ');
                }

                $liabilitas[$laporan] =
                    ($liabilitas[$laporan] ?? 0) + $nilai;
            }
        }

        /* ===== SECTION ASET ===== */
        $rows[] = ['Aset', '', ''];
        foreach ($aset as $nama => $total) {
            $rows[] = [$nama, 'Aset', $total];
        }
        $rows[] = ['Total Aset', '', array_sum($aset)];
        $rows[] = [];

        /* ===== SECTION LIABILITAS ===== */
        $rows[] = ['Liabilitas', '', ''];
        foreach ($liabilitas as $nama => $total) {
            $rows[] = [$nama, 'Liabilitas', $total];
        }
        $rows[] = ['Total Liabilitas', '', array_sum($liabilitas)];
        $rows[] = [];

        return $rows;
    }

    public function title(): string
    {
        return 'Laporan Aset';
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
