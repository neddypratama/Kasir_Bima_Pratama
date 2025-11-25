<?php

namespace App\Livewire;

use App\Models\Transaksi;
use App\Models\Kategori;
use App\Models\Barang;
use App\Models\JenisBarang;
use Livewire\Volt\Component;
use App\Exports\LabaRugiExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $startDate;
    public $endDate;

    public $pendapatanData = [];
    public $pengeluaranData = [];
    public $expanded = []; // toggle detail
    public $bebanPajak = 0;

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
        // Ambil tanggal paling awal dan paling akhir di tabel transaksi
        $firstTransaction = Transaksi::orderBy('tanggal', 'asc')->first();
        $lastTransaction = Transaksi::orderBy('tanggal', 'desc')->first();

        if (!$firstTransaction || !$lastTransaction) {
            $this->pendapatanData = [];
            $this->pengeluaranData = [];
            return;
        }

        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::parse($firstTransaction->tanggal)->startOfDay();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::parse($lastTransaction->tanggal)->endOfDay();

        // Mapping kelompok
        $mappingPendapatan = [
            'Penjualan Telur' => ['Penjualan Telur Horn', 'Penjualan Telur Bebek', 'Penjualan Telur Puyuh', 'Penjualan Telur Arab', 'Penjualan Telur Asin'],
            'Penjualan Pakan' => ['Penjualan Pakan Sentrat/Pabrikan', 'Penjualan Pakan Kucing', 'Penjualan Pakan Curah'],
            'Penjualan Obat' => ['Penjualan Obat-Obatan'],
            'Penjualan Eggtray' => ['Penjualan EggTray'],
            'Pendapatan Truk' => ['Pendapatan Truk'],
            'Pendapatan Perlengkapan' => ['Penjualan Triplex', 'Penjualan Terpal', 'Penjualan Ban Bekas', 'Penjualan Sak Campur', 'Penjualan Tali'],
            'Pendapatan Non Penjualan' => ['Pemasukan Dapur', 'Pemasukan Transport Setoran', 'Pemasukan Transport Pedagang'],
            'Pendapatan Lain-Lain' => ['Penjualan Lain-Lain'],
        ];

        $mappingPengeluaran = [
            'HPP Telur' => ['HPP Telur Horn', 'HPP Telur Bebek', 'HPP Telur Puyuh', 'HPP Telur Arab', 'HPP Telur Asin'],
            'HPP Pakan' => ['HPP Pakan Sentrat/Pabrikan', 'HPP Pakan Kucing', 'HPP Pakan Curah'],
            'HPP Obat' => ['HPP Obat-Obatan'],
            'HPP Eggtray' => ['HPP Tray'],
            'Pengeluaran Truk' => ['Pengeluaran Truk'],
            'Beban Transport' => ['Beban Transport', 'Beban BBM'],
            'Beban Operasional' => ['Beban Kantor', 'Beban Gaji', 'Beban Konsumsi', 'Peralatan', 'Perlengkapan', 'Beban Servis', 'Beban TAL'],
            'Beban Produksi' => ['Beban Telur Bentes', 'Beban Telur Ceplok', 'Beban Telur Prok', 'Beban Tray Terpakai', 'Beban Barang Kadaluarsa'],
            'Beban Bunga & Pajak' => ['Beban Bunga', 'Beban Pajak Kendaraan',],
            'Beban Sedekah' => ['ZIS'],
            'Beban Lain-Lain' => ['Beban Lain-Lain'],
        ];

        // --- Pendapatan per kategori ---
        $pendapatanFlat = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Pendapatan'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Pendapatan')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(fn($group) => $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total') - $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total'))
            ->toArray();

        // --- Pengeluaran per kategori umum ---
        $pengeluaranFlat = Transaksi::with('details.kategori', 'details.barang')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Pengeluaran'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Pengeluaran')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(fn($group) => $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total') - $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total'))
            ->toArray();

        // --- KHUSUS HPP: perhitungan berdasarkan jenis barang ---

        // Mapping kelompok HPP utama
        $hppKelompok = [
            'HPP Telur' => ['HPP Telur Horn', 'HPP Telur Bebek', 'HPP Telur Puyuh', 'HPP Telur Arab', 'HPP Telur Asin'],
            'HPP Pakan' => ['HPP Pakan Sentrat/Pabrikan', 'HPP Pakan Kucing', 'HPP Pakan Curah'],
            'HPP Obat' => ['HPP Obat-Obatan'],
            'HPP Eggtray' => ['HPP Tray'],
        ];

        // Ambil hasil total HPP per jenis barang langsung dari DB
        $hppResults = DB::table('detail_transaksis as td')
            ->join('kategoris as k', 'k.id', '=', 'td.kategori_id')
            ->join('barangs as b', 'b.id', '=', 'td.barang_id')
            ->join('jenis_barangs as jb', 'jb.id', '=', 'b.jenis_id')
            ->join('transaksis as t', 't.id', '=', 'td.transaksi_id')
            ->select(DB::raw("CONCAT('HPP ', jb.name) AS hpp_name"), 'jb.name as jenis_name', DB::raw('SUM(td.sub_total) AS total_hpp'))
            ->where('k.name', 'HPP')
            ->whereBetween('t.tanggal', [$start, $end])
            ->groupBy('jb.name')
            ->orderBy('jb.name')
            ->get()
            ->keyBy('hpp_name'); // supaya mudah diakses per nama HPP
        // dd($hppResults);

        // Siapkan struktur pengeluaranFlat sesuai kelompok
        foreach ($hppKelompok as $kelompok => $jenisList) {
            $detail = [];
            $total = 0;

            foreach ($jenisList as $jenis) {
                $hppName = $jenis; // ← perbaikan di sini
                $nilai = $hppResults[$hppName]->total_hpp ?? 0;
                $detail[$hppName] = $nilai;
                $total += $nilai;
            }

            // Simpan dalam pengeluaranFlat (mengikuti format laporan laba rugi)
            $pengeluaranFlat[$kelompok] = [
                'total' => $total,
                'detail' => $detail,
            ];
        }

        // Beban Pajak
        $this->bebanPajak = $pengeluaranFlat['Beban Pajak Pendapatan'] ?? 0;

        // --- Kelompokkan pendapatan ---
        $this->pendapatanData = [];
        foreach ($mappingPendapatan as $kelompok => $subs) {
            $detail = [];
            $total = 0;
            foreach ($subs as $sub) {
                $nilai = $pendapatanFlat[$sub] ?? 0;
                $detail[$sub] = $nilai;
                $total += $nilai;
            }
            $this->pendapatanData[$kelompok] = ['total' => $total, 'detail' => $detail];
        }

        // --- Kelompokkan pengeluaran ---
        $this->pengeluaranData = [];

        foreach ($mappingPengeluaran as $kelompok => $subs) {
            $detail = [];
            $total = 0;

            foreach ($subs as $sub) {
                $nilai = 0;

                // 1️⃣ Jika subkategori langsung ada di pengeluaranFlat
                if (isset($pengeluaranFlat[$sub])) {
                    $nilai = $pengeluaranFlat[$sub];
                }
                // 2️⃣ Jika tidak ada di level utama, cek di dalam HPP (bertumpuk)
                else {
                    foreach ($pengeluaranFlat as $kelompokHPP => $dataHPP) {
                        // pastikan struktur array-nya memiliki 'detail'
                        if (isset($dataHPP['detail'][$sub])) {
                            $nilai = $dataHPP['detail'][$sub];
                            break; // stop setelah ketemu
                        }
                    }
                }

                // 3️⃣ Tambahkan ke detail & total
                $detail[$sub] = $nilai;
                $total += $nilai;
            }

            // Simpan hasil akhir kategori besar
            $this->pengeluaranData[$kelompok] = [
                'total' => $total,
                'detail' => $detail,
            ];
        }
    }

    public function with()
    {
        $totalPendapatan = array_sum(array_map(fn($d) => $d['total'], $this->pendapatanData));
        $totalPengeluaran = array_sum(array_map(fn($d) => $d['total'], $this->pengeluaranData));
        $labaSebelumPajak = $totalPendapatan - $totalPengeluaran;
        $labaSetelahPajak = $labaSebelumPajak - $this->bebanPajak;

        return [
            'pendapatanData' => $this->pendapatanData,
            'pengeluaranData' => $this->pengeluaranData,
            'totalPendapatan' => $totalPendapatan,
            'totalPengeluaran' => $totalPengeluaran,
            'labaSebelumPajak' => $labaSebelumPajak,
            'bebanPajak' => $this->bebanPajak,
            'labaSetelahPajak' => $labaSetelahPajak,
        ];
    }
};
?>
<div class="p-6 space-y-6">
    <x-header title="Laporan Laba Rugi" separator>
        <x-slot:actions>
            <x-button wire:click="export" icon="fas.download" primary>Export Excel</x-button>
            <div class="flex grid grid-cols-1 md:grid-cols-2 items-end">
                <x-input type="date" label="Dari Tanggal" wire:model.live="startDate" />
                <x-input type="date" label="Sampai Tanggal" wire:model.live="endDate" />
            </div>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-card>
            <h3 class="text-lg font-semibold text-green-800">
                <i class="fas fa-coins text-green-600"></i>Total Pendapatan
            </h3>
            <p class="text-2xl font-bold text-green-700 mt-2">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-red-800">
                <i class="fas fa-wallet text-red-600"></i>Total Pengeluaran
            </h3>
            <p class="text-2xl font-bold text-red-700 mt-2">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold">
                <i class="fas fa-chart-line text-blue-600"></i>Laba Sebelum Pajak
            </h3>
            <p class="text-2xl font-bold {{ $labaSebelumPajak >= 0 ? 'text-green-700' : 'text-red-700' }} mt-2">
                Rp {{ number_format($labaSebelumPajak, 0, ',', '.') }}
            </p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold">
                <i class="fas fa-calculator text-purple-600"></i>Laba Setelah Pajak
            </h3>
            <p class="text-2xl font-bold {{ $labaSetelahPajak >= 0 ? 'text-green-700' : 'text-red-700' }} mt-2">
                Rp {{ number_format($labaSetelahPajak, 0, ',', '.') }}
            </p>
            <p class="text-sm text-gray-500 mt-1">(Beban Pajak: Rp {{ number_format($bebanPajak, 0, ',', '.') }})</p>
        </x-card>
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
