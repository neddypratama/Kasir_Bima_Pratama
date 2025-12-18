<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LabaRugiExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component {
    public $startDate;
    public $endDate;

    public $pendapatanData = [];
    public $pengeluaranData = [];
    public $expanded = []; // toggle detail

    public function mount()
    {
        $this->startDate = null;
        $this->endDate = null;
        $this->generateReport();
    }

    public function updated($field)
    {
        if (in_array($field, ['startDate', 'endDate'])) {
            $this->generateReport();
        }
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new LabaRugiExport($this->startDate, $this->endDate), 'laba_rugi.xlsx');
    }

    public function generateReport()
    {
        $firstTransaction = Transaksi::orderBy('invoice', 'asc')->first();
        $lastTransaction = Transaksi::orderBy('invoice', 'desc')->first();

        if (!$firstTransaction || !$lastTransaction) {
            $this->pendapatanData = [];
            $this->pengeluaranData = [];
            return;
        }

        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::parse($firstTransaction->tanggal)->startOfDay();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::parse($lastTransaction->tanggal)->endOfDay();

        // 🟢 Ambil semua jenis barang (master)
        $masterJenis = DB::table('jenis_barangs')->orderBy('name', 'asc')->pluck('name');

        // 🟡 Query Penjualan per jenis barang via type penjualan
        $penjualanResults = DB::table('detail_transaksis as td')
            ->join('barangs as b', 'b.id', '=', 'td.barang_id')
            ->join('jenis_barangs as jb', 'jb.id', '=', 'b.jenis_id')
            ->join('transaksis as t', 't.id', '=', 'td.transaksi_id')
            ->select(DB::raw('jb.name AS jenis_name'), DB::raw('SUM(td.sub_total) AS total_jual'))
            ->where('t.type', 'LIKE', 'Kredit')
            ->where('t.status', 'LIKE', 'Lunas')
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('jb.name')
            ->pluck('total_jual', 'jenis_name');

        // 🔴 Query HPP per jenis barang via type HPP
        $hppResults = DB::table('detail_transaksis as td')
            ->join('barangs as b', 'b.id', '=', 'td.barang_id')
            ->join('jenis_barangs as jb', 'jb.id', '=', 'b.jenis_id')
            ->join('transaksis as t', 't.id', '=', 'td.transaksi_id')
            ->select(DB::raw('jb.name AS jenis_name'), DB::raw('SUM(td.sub_total) AS total_hpp'))
            ->where('t.type', 'LIKE', 'Debit')
            ->where('t.status', 'LIKE', 'Lunas')
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('jb.name')
            ->pluck('total_hpp', 'jenis_name');

        // ⚪ Merge semua jenis barang + nilai Jual & HPP
        $report = [];
        foreach ($masterJenis as $jenis) {
            $jual = $penjualanResults[$jenis] ?? 0;
            $hpp = $hppResults[$jenis] ?? 0;

            $report[$jenis] = [
                'jual' => (float) $jual,
                'hpp' => (float) $hpp,
                'laba' => (float) $jual - (float) $hpp,
            ];
        }

        // ------------------ MAPPING YANG KAMU MAU ------------------ //

        $mappingPendapatan = [
            'Penjualan Pakan' => ['Hijauan', 'Konsentrat', 'Bahan Baku Konsentrat', 'Premix', 'Pakan Kucing'],
            'Penjualan Obat' => ['Obat-Obatan RMN', 'Obat-Obatan Unggas'],
            'Penjualan Barang' => ['Barang'],
        ];

        $mappingPengeluaran = [
            'HPP Pakan' => ['Hijauan', 'Konsentrat', 'Bahan Baku Konsentrat', 'Premix', 'Pakan Kucing'],
            'HPP Obat' => ['Obat-Obatan RMN', 'Obat-Obatan Unggas'],
            'HPP Barang' => ['Barang'],
        ];

        // kelompokkan pendapatan
        $this->pendapatanData = [];
        foreach ($mappingPendapatan as $kelompok => $jenisArray) {
            $detail = [];
            $total = 0;

            foreach ($jenisArray as $jenis) {
                $jumlah = $report[$jenis]['jual'] ?? 0;
                $detail['Penjualan ' . $jenis] = $jumlah;
                $total += $jumlah;
            }

            $this->pendapatanData[$kelompok] = [
                'total' => $total,
                'detail' => $detail,
            ];

            $this->expanded[$kelompok] = false;
        }

        // kelompokkan pengeluaran HPP
        $this->pengeluaranData = [];
        foreach ($mappingPengeluaran as $kelompok => $jenisArray) {
            $detail = [];
            $total = 0;

            foreach ($jenisArray as $jenis) {
                $jumlah = $report[$jenis]['hpp'] ?? 0;
                $detail['HPP ' . $jenis] = $jumlah;
                $total += $jumlah;
            }

            $this->pengeluaranData[$kelompok] = [
                'total' => $total,
                'detail' => $detail,
            ];

            $this->expanded[$kelompok] = false;
        }
    }

    public function with(): array
    {
        $totalPendapatan = array_sum(array_map(fn($d) => $d['total'], $this->pendapatanData));
        $totalPengeluaran = array_sum(array_map(fn($d) => $d['total'], $this->pengeluaranData));
        $totalLaba = $totalPendapatan - $totalPengeluaran;
        return [
            'pendapatanData' => $this->pendapatanData,
            'pengeluaranData' => $this->pengeluaranData,
            'totalPendapatan' => $totalPendapatan,
            'totalPengeluaran' => $totalPengeluaran,
            'totalLaba' => $totalLaba,
        ];
    }
};
?>
<div class="p-6 space-y-6">
    <x-header title="Laporan Laba Rugi" separator>
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
                <i class="fas fa-coins text-green-600"></i> Total Pendapatan
            </h3>
            <p class="text-2xl font-bold text-green-700 mt-2">
                Rp {{ number_format($totalPendapatan, 0, ',', '.') }}
            </p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-red-800">
                <i class="fas fa-wallet text-red-600"></i> Total Pengeluaran
            </h3>
            <p class="text-2xl font-bold text-red-700 mt-2">
                Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}
            </p>
        </x-card>
        @if ($totalLaba > 0)
            <x-card>
                <h3 class="text-lg font-semibold text-green-800">
                    <i class="fas fa-wallet text-green-600"></i> Total  
                </h3>
                <p class="text-2xl font-bold text-green-700 mt-2">
                    Rp {{ number_format($totalLaba, 0, ',', '.') }}
                </p>
            </x-card>
        @else
            <x-card>
                <h3 class="text-lg font-semibold text-red-800">
                    <i class="fas fa-wallet text-red-600"></i> Total Laba
                </h3>
                <p class="text-2xl font-bold text-red-700 mt-2">
                    Rp {{ number_format($totalLaba, 0, ',', '.') }}
                </p>
            </x-card>
        @endif
    </div>

    <x-card class="mt-4">
        <h3 class="text-xl font-semibold mb-4"><i class="fas fa-list-ul"></i>Rincian</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-lg font-semibold text-green-700 mb-2"><i class="fas fa-arrow-up"></i>Pendapatan per
                    Kelompok</h4>
                <ul class="divide-y divide-gray-200">
                    @foreach ($pendapatanData as $kelompok => $data)
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

            <div>
                <h4 class="text-lg font-semibold text-red-700 mb-2"><i class="fas fa-arrow-down"></i>Pengeluaran per
                    Kelompok</h4>
                <ul class="divide-y divide-gray-200">
                    @foreach ($pengeluaranData as $kelompok => $data)
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
