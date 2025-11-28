<?php

namespace App\Livewire;

use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\User;
use App\Models\JenisBarang;
use App\Models\Kategori;
use App\Models\Barang;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Carbon\Carbon;

new class extends Component {
    use Toast;

    public string $period = 'month';
    public $startDate;
    public $endDate;
    public array $pendapatanChart = [];
    public array $pengeluaranChart = [];
    public array $stokTelurChart = [];
    public array $stokSentratChart = [];
    public array $stokObatChart = [];
    public array $stokTrayChart = [];

    public ?int $selectedKategoriPendapatan = null;
    public array $kategoriPendapatanList = [];

    public ?int $selectedKategoriPengeluaran = null;
    public array $kategoriPengeluaranList = [];

    public function mount()
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->kategoriPendapatanList = Barang::all()->toArray();
        $this->kategoriPengeluaranList = Barang::all()->toArray();
        $this->setDefaultDates();
        $this->chartPendapatan();
        $this->chartPengeluaran();
        $this->chartStokTelur();
        $this->chartStokSentrat();
        $this->chartStokTray();
        $this->chartStokObat();
    }

    protected function setDefaultDates()
    {
        $now = Carbon::now();

        switch ($this->period) {
            case 'today':
                // Dari jam 06:00 sampai sekarang
                $this->startDate = $now->copy()->startOfDay()->addHours(6);
                $this->endDate = $now->copy(); // jam sekarang
                break;
            case 'week':
                $this->startDate = $now->copy()->startOfWeek();
                $this->endDate = $now->copy()->endOfWeek();
                break;
            case 'month':
                $this->startDate = $now->copy()->startOfMonth();
                $this->endDate = $now->copy()->endOfMonth();
                break;
            case 'year':
                $this->startDate = $now->copy()->startOfYear();
                $this->endDate = $now->copy()->endOfYear();
                break;
            default:
                $this->startDate = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : $now->copy()->startOfMonth();
                $this->endDate = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : $now->copy()->endOfMonth();
        }
    }

    public function updatedPeriod()
    {
        $this->setDefaultDates();
        $this->chartPendapatan();
        $this->chartPengeluaran();
        $this->chartStokTelur();
        $this->chartStokSentrat();
        $this->chartStokTray();
        $this->chartStokObat();
    }

    public function applyDateRange()
    {
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $this->period = 'custom';
        $this->startDate = Carbon::parse($this->startDate)->startOfDay();
        $this->endDate = Carbon::parse($this->endDate)->endOfDay();

        $this->chartPendapatan();
        $this->chartPengeluaran();
        $this->chartStokTelur();
        $this->chartStokSentrat();
        $this->chartStokTray();
        $this->chartStokObat();
        $this->toast('Periode tanggal berhasil diperbarui', 'success');
    }

    public function chartPendapatan()
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        $query = Transaksi::with(['details'])
            ->whereBetween('tanggal', [$start, $end])
            ->where('type', 'Kredit');
        // ->where('status', 'Lunas');
        if ($this->selectedKategoriPendapatan) {
            $query->whereHas('details', fn($q) => $q->where('barang_id', $this->selectedKategoriPendapatan));
        }

        $transactions = $query->orderBy('tanggal')->get();

        $labels = [];
        $incomeData = [];

        if ($this->period == 'today') {
            // per jam: 0 - 23
            for ($h = 0; $h <= 23; $h++) {
                $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                $hourTransactions = $transactions->filter(fn($trx) => Carbon::parse($trx->tanggal)->hour == $h);
                $totalDebit = $hourTransactions->where('type', 'Debit')->sum('total');
                $totalKredit = $hourTransactions->where('type', 'Kredit')->sum('total');
                $incomeData[] = $totalKredit - $totalDebit;
            }
        } else {
            // per hari
            $grouped = $transactions->groupBy(fn($trx) => Carbon::parse($trx->tanggal)->format('Y-m-d'));
            $periodRange = \Carbon\CarbonPeriod::create($start, $end);
            foreach ($periodRange as $date) {
                $labels[] = $date->format('Y-m-d');
                $dayTransactions = $grouped->get($date->format('Y-m-d'), collect());
                $totalDebit = $dayTransactions->where('type', 'Debit')->sum('total');
                $totalKredit = $dayTransactions->where('type', 'Kredit')->sum('total');
                $incomeData[] = $totalKredit - $totalDebit;
            }
        }

        $this->pendapatanChart = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Penjualan per ' . $this->period,
                        'data' => $incomeData,
                        'borderColor' => '#4CAF50',
                        'backgroundColor' => 'rgba(76, 175, 80, 0.2)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                ],
            ],
        ];
    }

    public function chartPengeluaran()
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        $query = Transaksi::with(['details'])
            ->whereBetween('tanggal', [$start, $end])
            ->where('type', 'Stok');
        // ->where('status', 'Lunas');

        if ($this->selectedKategoriPengeluaran) {
            $query->whereHas('details', fn($q) => $q->where('barang_id', $this->selectedKategoriPengeluaran));
        }

        $transactions = $query->orderBy('tanggal')->get();

        $labels = [];
        $expenseData = [];

        if ($this->period == 'today') {
            for ($h = 0; $h <= 23; $h++) {
                $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                $hourTransactions = $transactions->filter(fn($trx) => Carbon::parse($trx->tanggal)->hour == $h);
                $totalDebit = $hourTransactions->where('type', 'Stok')->sum('total');
                $expenseData[] = $totalDebit;
            }
        } else {
            $grouped = $transactions->groupBy(fn($trx) => Carbon::parse($trx->tanggal)->format('Y-m-d'));
            $periodRange = \Carbon\CarbonPeriod::create($start, $end);
            foreach ($periodRange as $date) {
                $labels[] = $date->format('Y-m-d');
                $dayTransactions = $grouped->get($date->format('Y-m-d'), collect());
                $totalDebit = $dayTransactions->where('type', 'Stok')->sum('total');
                $expenseData[] = $totalDebit;
            }
        }

        $this->pengeluaranChart = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Pembelian per ' . $this->period,
                        'data' => $expenseData,
                        'borderColor' => '#F44336',
                        'backgroundColor' => 'rgba(244, 67, 54, 0.2)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                ],
            ],
        ];
    }

    public function chartStokTelur()
    {
        $telurIds = JenisBarang::where('name', 'like', '%Kucing%')->pluck('id');
        $this->stokTelurChart = $this->generateChartDataPie($telurIds, 'Stok Pakan Kucing');
    }

    /**
     * Chart stok untuk jenis barang "Sentrat"
     */
    public function chartStokSentrat()
    {
        $sentratIds = JenisBarang::where('name', 'like', '%Sentrat%')->pluck('id');
        $this->stokSentratChart = $this->generateChartDataBar($sentratIds, 'Stok Sentrat/Pabrikan');
    }

    /**
     * Chart stok untuk jenis barang "Obat"
     */
    public function chartStokObat()
    {
        $obatIds = JenisBarang::where('name', 'like', '%Obat%')->pluck('id');
        $this->stokObatChart = $this->generateChartDataBar($obatIds, 'Stok Obat-Obatan');
    }

    /**
     * Chart stok untuk jenis barang "Tray"
     */
    public function chartStokTray()
    {
        $trayIds = JenisBarang::where('name', 'like', '%Curah%')->pluck('id');
        $this->stokTrayChart = $this->generateChartDataPie($trayIds, 'Stok Pakan Curah');
    }

    /**
     * Fungsi helper untuk membuat chart data dari kumpulan jenis barang
     */
    private function generateChartDataPie($jenisIds, $judul)
    {
        if ($jenisIds->isEmpty()) {
            return [];
        }

        $barangs = Barang::select('id', 'name', 'stok')->where('stok', '>', 0)->whereIn('jenis_id', $jenisIds)->get();

        if ($barangs->isEmpty()) {
            return [];
        }

        $grouped = $barangs->groupBy(fn($b) => $b->name);
        $data = $grouped->map(fn($items) => $items->sum('stok'))->toArray();

        $colors = collect($data)->map(fn() => sprintf('#%06X', mt_rand(0, 0xffffff)))->values()->toArray();

        return [
            'type' => 'pie',
            'data' => [
                'labels' => array_keys($data),
                'datasets' => [
                    [
                        'label' => $judul,
                        'data' => array_values($data),
                        'backgroundColor' => $colors,
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => $judul,
                    ],
                ],
            ],
        ];
    }

    private function generateChartDataBar($jenisIds, string $judul): array
    {
        if ($jenisIds->isEmpty()) {
            return [];
        }

        $barangs = Barang::select('id', 'name', 'stok')->where('stok', '>', 0)->whereIn('jenis_id', $jenisIds)->get();

        if ($barangs->isEmpty()) {
            return [];
        }

        $grouped = $barangs->groupBy(fn($b) => $b->name);
        $data = $grouped->map(fn($items) => $items->sum('stok'))->toArray();

        $colors = collect($data)->map(fn() => sprintf('#%06X', mt_rand(0, 0xffffff)))->values()->toArray();

        $labels = array_keys($data);

        // 🟢 Perbaikan bagian label — setiap barang jadi satu dataset sendiri
        $datasets = [];
        $index = 0;
        foreach ($data as $namaBarang => $stok) {
            $datasets[] = [
                'label' => $namaBarang, // ✅ label berbeda per barang
                'data' => [$stok],
                'backgroundColor' => $colors[$index] ?? '#4CAF50',
                'borderWidth' => 1,
            ];
            $index++;
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => [$judul], // cuma satu label utama (judul kategori)
                'datasets' => $datasets, // ✅ tiap barang punya label unik
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => $judul,
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];
    }

    public function incomeTotal(): int
    {
        $transaksis = Transaksi::where('type', 'Kredit')
            ->whereBetween('tanggal', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->get();

        $totalDebit = $transaksis->where('type', 'Debit')->sum('total');
        $totalKredit = $transaksis->where('type', 'Kredit')->sum('total');

        return $totalKredit - $totalDebit;
    }

    public function expenseTotal(): int
    {
        $transaksis = Transaksi::where('type', 'Stok')
            ->whereBetween('tanggal', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->get();

        $totalDebit = $transaksis->where('type', 'Stok')->sum('total');

        return $totalDebit;
    }

    public function assetTotal(): int
    {
        return Transaksi::whereBetween('tanggal', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])->count();
    }

    public function liabiliatsTotal(): int
    {
        return Barang::whereBetween('created_at', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])->count();
    }

    public function hutangPenjualan(): int
    {
        return Transaksi::where('type', 'Kredit')
            ->where('status', 'Hutang')
            ->whereBetween('tanggal', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->count();
    }

    public function hutangPembelian(): int
    {
        return Transaksi::where('type', 'Stok')
            ->where('status', 'Hutang')
            ->whereBetween('tanggal', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->count();
    }

    public function minimumStok(): int
    {
        return Barang::where('stok', '<=', 5)
            ->whereBetween('created_at', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->count();
    }

    public function updatedSelectedKategoriPendapatan()
    {
        $this->chartPendapatan();
    }

    public function updatedSelectedKategoriPengeluaran()
    {
        $this->chartPengeluaran();
    }

    public function with()
    {
        return [
            'incomeTotal' => $this->incomeTotal(),
            'expenseTotal' => $this->expenseTotal(),
            'assetTotal' => $this->assetTotal(),
            'liabiliatsTotal' => $this->liabiliatsTotal(),
            'hutangPenjualan' => $this->hutangPenjualan(),
            'hutangPembelian' => $this->hutangPembelian(),
            'minimumStok' => $this->minimumStok(),
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ];
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Dashboard" separator progress-indicator>
        <x-slot:actions>
            @php
                $periods = [
                    [
                        'id' => 'today',
                        'name' => 'Hari Ini',
                        'hint' => 'Data dalam 24 jam terakhir',
                        'icon' => 'o-clock',
                    ],
                    [
                        'id' => 'week',
                        'name' => 'Minggu Ini',
                        'hint' => 'Data minggu berjalan',
                        'icon' => 'o-calendar-days',
                    ],
                    ['id' => 'month', 'name' => 'Bulan Ini', 'hint' => 'Data bulan berjalan', 'icon' => 'o-chart-pie'],
                    ['id' => 'year', 'name' => 'Tahun Ini', 'hint' => 'Data tahun berjalan', 'icon' => 'o-chart-bar'],
                    [
                        'id' => 'custom',
                        'name' => 'Custom',
                        'hint' => 'Pilih rentang tanggal khusus',
                        'icon' => 'o-calendar',
                    ],
                ];
            @endphp

            <div class="flex flex-col gap-4">
                <x-select wire:model.live="period" :options="$periods" option-label="name" option-value="id"
                    option-description="hint" class="w-full" />

                @if ($period == 'custom')
                    <form wire:submit.prevent="applyDateRange" class="space-y-3">
                        <div class="flex flex-col md:flex-row gap-3">
                            <x-input type="date" label="Dari Tanggal" wire:model="startDate" :max="now()->format('Y-m-d')"
                                class="flex-1" />
                            <x-input type="date" label="Sampai Tanggal" wire:model="endDate" :min="$startDate"
                                :max="now()->format('Y-m-d')" class="flex-1" />
                        </div>
                        <x-button spinner label="Terapkan" type="submit" icon="o-check"
                            class="btn-primary w-full md:w-auto" />

                        @error('endDate')
                            <div class="text-red-500 text-sm">{{ $message }}</div>
                        @enderror

                        <div class="text-sm text-gray-500">
                            Periode terpilih:
                            {{ $startDate->translatedFormat('d M Y') }} - {{ $endDate->translatedFormat('d M Y') }}
                        </div>
                    </form>
                @endif
            </div>
        </x-slot:actions>
    </x-header>

    <!-- GRID UTAMA -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pendapatan -->
        <x-card class="rounded-lg shadow p-4">
            <div class="flex items-center justify-center gap-3">
                <x-icon name="fas.coins" class="text-purple-500 w-10 h-10 shrink-0" />
                <div>
                    <p class="text-sm">Hutang Penjualan</p>
                    <p class="text-xl font-bold">{{ number_format($hutangPenjualan) }}</p>
                </div>
            </div>
        </x-card>

        <!-- Pengeluaran -->
        <x-card class="rounded-lg shadow p-4">
            <div class="flex items-center justify-center gap-3">
                <x-icon name="fas.basket-shopping" class="text-blue-500 w-10 h-10 shrink-0" />
                <div>
                    <p class="text-sm">Hutang Pembelian</p>
                    <p class="text-xl font-bold">{{ number_format($hutangPembelian) }}</p>
                </div>
            </div>
        </x-card>

        <!-- Aset -->
        <x-card class="rounded-lg shadow p-4">
            <div class="flex items-center justify-center gap-3">
                <x-icon name="fas.cart-shopping" class="text-green-500 w-10 h-10 shrink-0" />
                <div>
                    <p class="text-sm">Total Transaksi</p>
                    <p class="text-xl font-bold">{{ number_format($assetTotal) }}</p>
                </div>
            </div>
        </x-card>

        <!-- Liabilitas -->
        <x-card class="rounded-lg shadow p-4">
            <div class="flex items-center justify-center gap-3">
                <x-icon name="fas.dolly" class="text-yellow-500 w-10 h-10 shrink-0" />
                <div>
                    <p class="text-sm">Stok Minimum</p>
                    <p class="text-xl font-bold">{{ number_format($minimumStok) }}</p>
                </div>
            </div>
        </x-card>
    </div>

    @if (Auth::user()->role_id == 1 || Auth::user()->role_id == 2)
        <!-- GRID UTAMA -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Pendapatan -->
            <x-card class="rounded-lg shadow p-4">
                <div class="flex items-center justify-center gap-3">
                    <x-icon name="fas.money-bill-wave" class="text-purple-500 w-10 h-10 shrink-0" />
                    <div>
                        <p class="text-sm">Total Penjualan</p>
                        <p class="text-xl font-bold">Rp. {{ number_format($incomeTotal) }}</p>
                    </div>
                </div>
            </x-card>

            <!-- Pengeluaran -->
            <x-card class="rounded-lg shadow p-4">
                <div class="flex items-center justify-center gap-3">
                    <x-icon name="fas.shopping-bag" class="text-blue-500 w-10 h-10 shrink-0" />
                    <div>
                        <p class="text-sm">Total Pembelian</p>
                        <p class="text-xl font-bold">Rp. {{ number_format($expenseTotal) }}</p>
                    </div>
                </div>
            </x-card>

            <!-- Aset -->
            <x-card class="rounded-lg shadow p-4">
                <div class="flex items-center justify-center gap-3">
                    <x-icon name="fas.cart-shopping" class="text-green-500 w-10 h-10 shrink-0" />
                    <div>
                        <p class="text-sm">Total Transaksi</p>
                        <p class="text-xl font-bold">{{ number_format($assetTotal) }}</p>
                    </div>
                </div>
            </x-card>

            <!-- Liabilitas -->
            <x-card class="rounded-lg shadow p-4">
                <div class="flex items-center justify-center gap-3">
                    <x-icon name="fas.box" class="text-yellow-500 w-10 h-10 shrink-0" />
                    <div>
                        <p class="text-sm">Total Barang</p>
                        <p class="text-xl font-bold">{{ number_format($liabiliatsTotal) }}</p>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- CHARTS -->
        <div class="grid grid-cols-1 lg:grid-cols-10 gap-4">
            <x-card class="col-span-10 overflow-x-auto">
                <x-slot:title>Grafik Penjualan</x-slot:title>
                <x-slot:menu>
                    <x-choices-offline label="Pilih Barang Penjualan" wire:model.live="selectedKategoriPendapatan"
                        :options="collect($kategoriPendapatanList)
                            ->map(fn($k) => ['id' => $k['id'], 'name' => $k['name']])
                            ->prepend(['id' => null, 'name' => 'Semua Penjualan'])" option-value="id" option-label="name" single searchable
                        class="w-full md:w-64" />
                </x-slot:menu>
                <div class="w-full min-w-[320px]">
                    <x-chart wire:model="pendapatanChart" />
                </div>
            </x-card>

            <x-card class="col-span-10 overflow-x-auto">
                <x-slot:title>Grafik Pembelian</x-slot:title>
                <x-slot:menu>
                    <x-choices-offline label="Pilih Barang Pembelian" wire:model.live="selectedKategoriPengeluaran"
                        :options="collect($kategoriPengeluaranList)
                            ->map(fn($k) => ['id' => $k['id'], 'name' => $k['name']])
                            ->prepend(['id' => null, 'name' => 'Semua Pembelian'])" option-value="id" option-label="name" single searchable
                        class="w-full md:w-64" />
                </x-slot:menu>
                <div class="w-full min-w-[320px]">
                    <x-chart wire:model="pengeluaranChart" />
                </div>
            </x-card>

            <!-- Stok Charts -->
            <x-card class="col-span-10 md:col-span-5 overflow-x-auto">
                <x-slot:title>Stok Pakan Kucing</x-slot:title>
                <div class="w-full min-w-[320px]">
                    <x-chart wire:model="stokTelurChart" />
                </div>
            </x-card>

            <x-card class="col-span-10 md:col-span-5 overflow-x-auto">
                <x-slot:title>Stok Pakan Curah</x-slot:title>
                <div class="w-full min-w-[320px]">
                    <x-chart wire:model="stokTrayChart" />
                </div>
            </x-card>

            <x-card class="col-span-10 overflow-x-auto">
                <x-slot:title>Stok Obat Obatan</x-slot:title>
                <div class="w-full min-w-[320px]">
                    <x-chart wire:model="stokObatChart" />
                </div>
            </x-card>

            <x-card class="col-span-10 overflow-x-auto">
                <x-slot:title>Stok Pakan Sentrat/Pabrikan</x-slot:title>
                <div class="w-full min-w-[320px]">
                    <x-chart wire:model="stokSentratChart" />
                </div>
            </x-card>
        </div>
    @endif

</div>
