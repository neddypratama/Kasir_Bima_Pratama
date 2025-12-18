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
    public $stokData = [];
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
        // ambil tanggal awal & akhir transaksi jika filter null
        $firstTransaction = Transaksi::orderBy('tanggal', 'asc')->first();
        $lastTransaction = Transaksi::orderBy('tanggal', 'desc')->first();

        if (!$firstTransaction || !$lastTransaction) {
            $this->pendapatanData = [];
            $this->pengeluaranData = [];
            return;
        }

        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::parse($firstTransaction->tanggal)->startOfDay();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::parse($lastTransaction->tanggal)->endOfDay();

        // 📌 MAPPING BESAR SESUAI PERMINTAAN
        $mappingStok = [
            'Stok Pakan' => ['Hijauan', 'Konsentrat', 'Bahan Baku Konsentrat', 'Premix', 'Pakan Kucing'],
            'Stok Obat' => ['Obat-Obatan RMN', 'Obat-Obatan Unggas'],
            'Stok Barang' => ['Barang'],
        ];

        $mappingPendapatan = [
            'Bon Pakan' => ['Hijauan', 'Konsentrat', 'Bahan Baku Konsentrat', 'Premix', 'Pakan Kucing'],
            'Bon Obat' => ['Obat-Obatan RMN', 'Obat-Obatan Unggas'],
            'Bon Barang' => ['Barang'],
        ];

        $mappingPengeluaran = [
            'Hutang Pakan' => ['Hijauan', 'Konsentrat', 'Bahan Baku Konsentrat', 'Premix', 'Pakan Kucing'],
            'Hutang Obat' => ['Obat-Obatan RMN', 'Obat-Obatan Unggas'],
            'Hutang Barang' => ['Barang'],
        ];

        // 🟢 Ambil semua jenis barang (master) agar 0 tetap tampil
        $masterJenis = DB::table('jenis_barangs')->orderBy('name', 'asc')->pluck('name');

        // 🟡 Query PENJUALAN (Pendapatan) per jenis barang
        $penjualanResults = DB::table('detail_transaksis as td')
            ->join('barangs as b', 'b.id', '=', 'td.barang_id')
            ->join('jenis_barangs as jb', 'jb.id', '=', 'b.jenis_id')
            ->join('transaksis as t', 't.id', '=', 'td.transaksi_id')
            ->select(DB::raw('jb.name AS jenis_name'), DB::raw('SUM(td.sub_total) AS total_jual'))
            ->where('t.type', 'LIKE', 'Kredit')
            ->where('t.status', 'LIKE', 'Hutang')
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('jb.name')
            ->pluck('total_jual', 'jenis_name');

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

        $stokResults = DB::table('detail_transaksis as td')
            ->join('barangs as b', 'b.id', '=', 'td.barang_id')
            ->join('jenis_barangs as jb', 'jb.id', '=', 'b.jenis_id')
            ->join('transaksis as t', 't.id', '=', 'td.transaksi_id')
            ->select(DB::raw('jb.name AS jenis_name'), DB::raw('SUM(td.sub_total) AS total_hpp'))
            ->where('t.type', 'LIKE', 'Stok')
            ->where('t.status', 'LIKE', 'Lunas')
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('jb.name')
            ->pluck('total_hpp', 'jenis_name');

        // 🔴 Query HPP (Pengeluaran) per jenis barang
        $hutangResults = DB::table('detail_transaksis as td')
            ->join('barangs as b', 'b.id', '=', 'td.barang_id')
            ->join('jenis_barangs as jb', 'jb.id', '=', 'b.jenis_id')
            ->join('transaksis as t', 't.id', '=', 'td.transaksi_id')
            ->select(DB::raw('jb.name AS jenis_name'), DB::raw('SUM(td.sub_total) AS total_hpp'))
            ->where('t.type', 'LIKE', 'Stok')
            ->where('t.status', 'LIKE', 'Hutang')
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('jb.name')
            ->pluck('total_hpp', 'jenis_name');

        // ⚪ Susun report final per master jenis barang
        $report = [];
        foreach ($masterJenis as $jenis) {
            $bon = (float) ($penjualanResults[$jenis] ?? 0);
            $hutang = (float) ($hutangResults[$jenis] ?? 0);
            $stok = (float) ($stokResults[$jenis] ?? 0);
            $hpp = (float) ($hppResults[$jenis] ?? 0);

            $report[$jenis] = [
                'bon' => $bon,
                'hutang' => $hutang,
                'stok' => $stok,
                'hpp' => $hpp,
            ];
        }

        // 🟢 BANGUN DATA PENDAPATAN SESUAI KELOMPOK MAPPING
        $this->pendapatanData = [];
        foreach ($mappingPendapatan as $kelompok => $jenisArray) {
            $detail = [];
            $total = 0;

            foreach ($jenisArray as $jenis) {
                $jumlah = $report[$jenis]['bon'] ?? 0;
                $key = "Bon $jenis";
                $detail[$key] = $jumlah;
                $total += $jumlah;
            }

            $this->pendapatanData[$kelompok] = [
                'total' => $total,
                'detail' => $detail,
            ];

            $this->expanded[$kelompok] = false;
        }

        // 🔴 BANGUN DATA PENGELUARAN SESUAI KELOMPOK MAPPING
        $this->pengeluaranData = [];
        foreach ($mappingPengeluaran as $kelompok => $jenisArray) {
            $detail = [];
            $total = 0;

            foreach ($jenisArray as $jenis) {
                $jumlah = $report[$jenis]['hutang'] ?? 0;
                $key = "Hutang $jenis";
                $detail[$key] = $jumlah;
                $total += $jumlah;
            }

            $this->pengeluaranData[$kelompok] = [
                'total' => $total,
                'detail' => $detail,
            ];

            $this->expanded[$kelompok] = false;
        }

        $this->stokData = [];
        foreach ($mappingStok as $kelompok => $jenisArray) {
            $detail = [];
            $total = 0;

            foreach ($jenisArray as $jenis) {
                $jumlah = ($report[$jenis]['stok'] ?? 0) - ($report[$jenis]['hpp'] ?? 0);
                $key = "Stok $jenis";
                $detail[$key] = $jumlah;
                $total += $jumlah;
            }

            $this->stokData[$kelompok] = [
                'total' => $total,
                'detail' => $detail,
            ];

            $this->expanded[$kelompok] = false;
        }
    }

    public function with(): array
    {
        $totalAset = array_sum(array_map(fn($d) => $d['total'], $this->pendapatanData) + array_map(fn($d) => $d['total'], $this->stokData));
        $totalLiabilitas = array_sum(array_map(fn($d) => $d['total'], $this->pengeluaranData));
        $totalModal = $totalAset - $totalLiabilitas;

        return [
            'pendapatanData' => $this->pendapatanData,
            'pengeluaranData' => $this->pengeluaranData,
            'stokData' => $this->stokData,
            'totalPendapatan' => $totalAset,
            'totalPengeluaran' => $totalLiabilitas,
            'totalModal' => $totalModal,
        ];
    }
};

?>
<div class="p-6 space-y-6">
    <x-header title="Laporan Aset per Jenis Barang" separator>
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
