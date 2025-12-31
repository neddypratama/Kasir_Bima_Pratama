<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    public ?string $startDate = null;
    public ?string $endDate = null;

    public array $bonData = [];
    public array $hutangData = [];
    public array $stokData = [];
    public array $expanded = [];

    /* ======================
        MOUNT
    ====================== */
    public function mount()
    {
        $this->generateReport();
    }

    public function updated($field)
    {
        if (in_array($field, ['startDate', 'endDate'])) {
            $this->generateReport();
        }
    }

    /* ======================
        GENERATE REPORT
    ====================== */
    public function generateReport()
    {
        $first = Transaksi::orderBy('tanggal')->first();
        $last = Transaksi::orderByDesc('tanggal')->first();

        if (!$first || !$last) {
            $this->bonData = [];
            $this->hutangData = [];
            $this->stokData = [];
            return;
        }

        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::parse($first->tanggal)->startOfDay();

        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::parse($last->tanggal)->endOfDay();

        /* =====================================================
            1. AMBIL LAPORAN + KATEGORI (MASTER)
        ===================================================== */
        $laporans = DB::table('laporans')->leftJoin('kategoris', 'kategoris.laporan_id', '=', 'laporans.id')->select('laporans.id as laporan_id', 'laporans.name as laporan', 'laporans.type', 'kategoris.id as kategori_id', 'kategoris.name as kategori')->orderBy('laporans.id')->orderBy('kategoris.name')->get();

        /* =====================================================
            2. INIT SEMUA DATA = 0
        ===================================================== */
        $this->bonData = [];
        $this->hutangData = [];
        $this->stokData = [];

        foreach ($laporans as $row) {
            /* ======================
                PENDAPATAN → BON
            ====================== */
            if ($row->type === 'Pendapatan') {
                if (Str::startsWith($row->laporan, 'Penjualan ')) {
                    $nama = Str::after($row->laporan, 'Penjualan ');
                    $row->laporan = 'Bon ' . $nama;
                }
                // ambil kata setelah "Penjualan "
                if (Str::startsWith($row->kategori, 'Penjualan ')) {
                    $nama = Str::after($row->kategori, 'Penjualan ');
                    $row->kategori = 'Bon ' . $nama;
                }

                $this->bonData[$row->laporan]['detail'][$row->kategori] = 0;
                $this->bonData[$row->laporan]['total'] ??= 0;
            }

            /* ======================
                ASET → STOK (ASLI)
            ====================== */
            if ($row->type === 'Aset') {
                $this->stokData[$row->laporan]['detail'][$row->kategori] = 0;
                $this->stokData[$row->laporan]['total'] ??= 0;
            }

            /* ======================
                ASET → HUTANG
            ====================== */
            if ($row->type === 'Aset') {
                if (Str::startsWith($row->laporan, 'Stok ')) {
                    $nama = Str::after($row->laporan, 'Stok ');
                    $row->laporan = 'Hutang ' . $nama;
                }
                // ambil kata setelah "Stok "
                if (Str::startsWith($row->kategori, 'Stok ')) {
                    $nama = Str::after($row->kategori, 'Stok ');
                    $row->kategori = 'Hutang ' . $nama;
                }

                $this->hutangData[$row->laporan]['detail'][$row->kategori] = 0;
                $this->hutangData[$row->laporan]['total'] ??= 0;
            }
        }

        // dd($this->bonData, $this->hutangData, $this->stokData, $laporans);

        /* =====================================================
    3. AMBIL NILAI TRANSAKSI
===================================================== */
        $rows = DB::table('detail_transaksis as dt')
            ->join('kategoris as k', 'k.id', '=', 'dt.kategori_id')
            ->join('laporans as l', 'l.id', '=', 'k.laporan_id')
            ->join('transaksis as t', 't.id', '=', 'dt.transaksi_id')
            ->select('l.name as laporan', 'l.type', 'k.name as kategori', 't.type as transaksi_type', 't.status', DB::raw('SUM(dt.sub_total) as total'))
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('l.name', 'l.type', 'k.name', 't.type', 't.status')
            ->get();

        /* =====================================================
    4. ISI NILAI KE STRUKTUR (TRANSFORM NAMA)
===================================================== */
        foreach ($rows as $row) {
            $laporan = $row->laporan;
            $kategori = $row->kategori;
            $nilai = (float) $row->total;

            /* ======================
        PENJUALAN → BON
        Kredit + Hutang
    ====================== */
            if ($row->type === 'Pendapatan' && $row->transaksi_type === 'Kredit' && $row->status === 'Hutang') {
                if (Str::startsWith($laporan, 'Penjualan ')) {
                    $laporan = 'Bon ' . Str::after($laporan, 'Penjualan ');
                }

                if (Str::startsWith($kategori, 'Penjualan ')) {
                    $kategori = 'Bon ' . Str::after($kategori, 'Penjualan ');
                }

                $this->bonData[$laporan]['detail'][$kategori] += $nilai;
                $this->bonData[$laporan]['total'] += $nilai;
            }

            /* ======================
        STOK LUNAS → ASET
    ====================== */
            if ($row->type === 'Aset' && $row->transaksi_type === 'Stok' && $row->status === 'Lunas') {
                $this->stokData[$laporan]['detail'][$kategori] += $nilai;
                $this->stokData[$laporan]['total'] += $nilai;
            }

            /* ======================
        STOK HUTANG → HUTANG
    ====================== */
            if ($row->type === 'Aset' && $row->transaksi_type === 'Stok' && $row->status === 'Hutang') {
                if (Str::startsWith($laporan, 'Stok ')) {
                    $laporan = 'Hutang ' . Str::after($laporan, 'Stok ');
                }

                if (Str::startsWith($kategori, 'Stok ')) {
                    $kategori = 'Hutang ' . Str::after($kategori, 'Stok ');
                }

                $this->hutangData[$laporan]['detail'][$kategori] += $nilai;
                $this->hutangData[$laporan]['total'] += $nilai;
            }
        }
    }

    public function with(): array
    {
        $totalPendapatan = array_sum(array_column($this->bonData, 'total'));
        $totalPengeluaran = array_sum(array_column($this->hutangData, 'total'));

        return [
            'bonData' => $this->bonData,
            'hutangData' => $this->hutangData,
            'totalPendapatan' => $totalPendapatan,
            'totalPengeluaran' => $totalPengeluaran,
            'totalModal' => $totalPendapatan - $totalPengeluaran,
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <x-header title="Laporan Aset" separator>
        <x-slot:actions>
            <x-button wire:click="export" icon="fas.download" primary>Export Excel</x-button>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 items-end">
                <x-input type="date" label="Dari Tanggal" wire:model.live="startDate" />
                <x-input type="date" label="Sampai Tanggal" wire:model.live="endDate" />
            </div>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-card>
            <h3 class="text-lg font-semibold text-green-800">
                <i class="fas fa-box"></i> Total Aset
            </h3>
            <p class="text-2xl font-bold text-green-700 mt-2">
                Rp {{ number_format($totalPendapatan, 0, ',', '.') }}
            </p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-red-800">
                <i class="fas fa-file-invoice"></i> Total Hutang
            </h3>
            <p class="text-2xl font-bold text-red-700 mt-2">
                Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}
            </p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold {{ $totalModal >= 0 ? 'text-green-800' : 'text-red-800' }}">
                <i class="fas fa-balance-scale"></i> Total Modal
            </h3>
            <p class="text-2xl font-bold {{ $totalModal >= 0 ? 'text-green-700' : 'text-red-700' }} mt-2">
                Rp {{ number_format($totalModal, 0, ',', '.') }}
            </p>
        </x-card>
    </div>

    <x-card class="mt-4">
        <h3 class="text-xl font-semibold mb-4"><i class="fas fa-list"></i>Rincian</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- LIST ASET (Pendapatan) -->
            <div>
                <h4 class="text-lg font-semibold text-green-700 mb-2"><i class="fas fa-arrow-up"></i>Aset</h4>
                <ul class="divide-y divide-gray-200">
                    @foreach ($bonData as $kelompok => $data)
                        <li class="py-2">
                            <div class="flex justify-between cursor-pointer"
                                wire:click="$toggle('expanded.{{ $kelompok }}')">
                                <span class="font-medium">{{ $kelompok }}</span>
                                <span class="text-green-700">Rp {{ number_format($data['total'], 0, ',', '.') }}</span>
                            </div>

                            @if ($expanded[$kelompok] ?? false)
                                <ul class="pl-4 mt-2">
                                    @foreach ($data['detail'] as $sub => $val)
                                        <li class="flex justify-between py-1 text-green-600">
                                            <span>{{ $sub }}</span>
                                            <span>Rp {{ number_format($val, 0, ',', '.') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                    @foreach ($stokData as $kelompok => $data)
                        <li class="py-2">
                            <div class="flex justify-between cursor-pointer"
                                wire:click="$toggle('expanded.{{ $kelompok }}')">
                                <span class="font-medium">{{ $kelompok }}</span>
                                <span class="text-green-700">Rp {{ number_format($data['total'], 0, ',', '.') }}</span>
                            </div>

                            @if ($expanded[$kelompok] ?? false)
                                <ul class="pl-4 mt-2">
                                    @foreach ($data['detail'] as $sub => $val)
                                        <li class="flex justify-between py-1 text-green-600">
                                            <span>{{ $sub }}</span>
                                            <span>Rp {{ number_format($val, 0, ',', '.') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- LIST HUTANG (Pengeluaran) -->
            <div>
                <h4 class="text-lg font-semibold text-red-700 mb-2"><i class="fas fa-arrow-down"></i>Liabilitas</h4>
                <ul class="divide-y divide-gray-200">
                    @foreach ($hutangData as $kelompok => $data)
                        <li class="py-2">
                            <div class="flex justify-between cursor-pointer"
                                wire:click="$toggle('expanded.{{ $kelompok }}')">
                                <span class="font-medium">{{ $kelompok }}</span>
                                <span class="text-red-700">Rp {{ number_format($data['total'], 0, ',', '.') }}</span>
                            </div>

                            @if ($expanded[$kelompok] ?? false)
                                <ul class="pl-4 mt-2">
                                    @foreach ($data['detail'] as $sub => $val)
                                        <li class="flex justify-between py-1 text-red-600">
                                            <span>{{ $sub }}</span>
                                            <span>Rp {{ number_format($val, 0, ',', '.') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </x-card>
</div>
