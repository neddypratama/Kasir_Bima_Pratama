<?php

namespace App\Livewire;

use App\Models\Transaksi;
use App\Models\Kategori;
use Livewire\Volt\Component;
use App\Exports\AsetExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component {
    public $startDate;
    public $endDate;

    public $asetData = [];
    public $liabilitasData = [];
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
        return Excel::download(new AsetExport($this->startDate, $this->endDate), 'aset.xlsx');
    }

    public function generateReport()
    {
        // Ambil tanggal paling awal dan paling akhir di tabel transaksi
        $firstTransaction = Transaksi::orderBy('tanggal', 'asc')->first();
        $lastTransaction = Transaksi::orderBy('tanggal', 'desc')->first();

        // Jika tidak ada data sama sekali
        if (!$firstTransaction || !$lastTransaction) {
            $this->asetData = [];
            $this->liabilitasData = [];
            return;
        }

        // Gunakan tanggal transaksi pertama & terakhir jika tanggal tidak diisi
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::parse($firstTransaction->tanggal)->startOfDay();

        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::parse($lastTransaction->tanggal)->endOfDay();

        // Semua kategori
        $kategoriAset = Kategori::where('type', 'Aset')->pluck('name')->toArray();
        $kategoriLiabilitas = Kategori::where('type', 'Liabilitas')->pluck('name')->toArray();

        // Mapping kelompok
        $mappingAset = [
            'Piutang Pihak Lain' => ['Piutang Peternak', 'Piutang Karyawan', 'Piutang Pedagang'],
            'Piutang Supplier' => ['Supplier Bp.Supriyadi'],
            'Piutang Tray' => ['Piutang Tray Diamond /DM', 'Piutang Tray Super Buah /SB', 'Piutang Tray Random'],
            'Piutang Obat' => ['Piutang Obat SK', 'Piutang Obat Ponggok', 'Piutang Obat Random'],
            'Piutang Sentrat' => ['Piutang Sentrat SK', 'Piutang Sentrat Ponggok', 'Piutang Sentrat Random'],
            'Stok' => ['Stok Telur', 'Stok Pakan', 'Stok Obat-Obatan', 'Stok Tray', 'Stok Kotor', 'Stok Return'],
            'Kas' => ['Kas Tunai'],
            'Bank BCA' => ['Bank BCA Binti Wasilah', 'Bank BCA Masduki'],
            'Bank BNI' => ['Bank BNI Binti Wasilah', 'Bank BNI Bima Pratama'],
            'Bank BRI' => ['Bank BRI Binti Wasilah', 'Bank BRI Masduki'],
        ];

        $mappingLiabilitas = [
            'Hutang Pihak Lain' => ['Hutang Peternak', 'Hutang Karyawan', 'Hutang Pedagang', 'Hutang Bank'],
            'Hutang Supplier' => ['Saldo Bp.Supriyadi'],
            'Hutang Tray' => ['Hutang Tray Diamond /DM', 'Hutang Tray Super Buah /SB', 'Piutang Tray Random'],
            'Hutang Obat' => ['Hutang Obat SK', 'Hutang Obat Ponggok', 'Hutang Obat Random'],
            'Hutang Sentrat' => ['Hutang Sentrat SK', 'Hutang Sentrat Ponggok', 'Hutang Sentrat Random'],
        ];

        // --- Aset per kategori ---
        $asetFlat = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Aset'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Aset')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(fn($group) => $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total') - $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total'))
            ->toArray();

        // --- Liabilitas per kategori ---
        $liabilitasFlat = Transaksi::with('details.kategori')
            ->whereHas('details.kategori', fn($q) => $q->where('type', 'Liabilitas'))
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->flatMap(fn($trx) => $trx->details)
            ->filter(fn($d) => $d->kategori && $d->kategori->type == 'Liabilitas')
            ->groupBy(fn($d) => $d->kategori->name)
            ->map(fn($group) => $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'kredit')->sum('sub_total') - $group->filter(fn($d) => strtolower($d->transaksi->type ?? '') == 'debit')->sum('sub_total'))
            ->toArray();

        // --- Kelompokkan Aset ---
        $this->asetData = [];
        foreach ($mappingAset as $kelompok => $subs) {
            $detail = [];
            $total = 0;
            foreach ($subs as $sub) {
                $nilai = $asetFlat[$sub] ?? 0;
                $detail[$sub] = $nilai;
                $total += $nilai;
            }
            $this->asetData[$kelompok] = ['total' => $total, 'detail' => $detail];
        }

        // --- Kelompokkan Liabilitas ---
        $this->liabilitasData = [];
        foreach ($mappingLiabilitas as $kelompok => $subs) {
            $detail = [];
            $total = 0;
            foreach ($subs as $sub) {
                $nilai = $liabilitasFlat[$sub] ?? 0;
                $detail[$sub] = $nilai;
                $total += $nilai;
            }
            $this->liabilitasData[$kelompok] = ['total' => $total, 'detail' => $detail];
        }
    }

    public function with()
    {
        $totalAset = array_sum(array_map(fn($d) => $d['total'], $this->asetData));
        $totalLiabilitas = array_sum(array_map(fn($d) => $d['total'], $this->liabilitasData));

        return [
            'asetData' => $this->asetData,
            'liabilitasData' => $this->liabilitasData,
            'totalAset' => $totalAset,
            'totalLiabilitas' => $totalLiabilitas,
            'totalModal' => $totalAset - $totalLiabilitas,
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <x-header title="Laporan Aset" separator>
        <x-slot:actions>
            <x-button wire:click="export" icon="fas.download" primary>Export Excel</x-button>
            <div class="flex grid grid-cols-1 md:grid-cols-2 items-end">
                <x-input type="date" label="Dari Tanggal" wire:model.live="startDate" />
                <x-input type="date" label="Sampai Tanggal" wire:model.live="endDate" />
            </div>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-card>
            <h3 class="text-lg font-semibold text-green-800">
                <i class="fas fa-coins text-green-600"></i> Total Aset
            </h3>
            <p class="text-2xl font-bold text-green-700 mt-2">Rp {{ number_format($totalAset, 0, ',', '.') }}</p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-red-800">
                <i class="fas fa-wallet text-red-600"></i> Total Liabilitas
            </h3>
            <p class="text-2xl font-bold text-red-700 mt-2">Rp {{ number_format($totalLiabilitas, 0, ',', '.') }}</p>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-blue-800">
                <i class="fas fa-wallet text-blue-600"></i> Total Modal
            </h3>
            <p class="text-2xl font-bold text-blue-700 mt-2">Rp {{ number_format($totalModal, 0, ',', '.') }}</p>
        </x-card>
    </div>

    <x-card class="mt-4">
        <h3 class="text-xl font-semibold mb-4"><i class="fas fa-list-ul"></i> Rincian</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Aset -->
            <div>
                <h4 class="text-lg font-semibold text-green-700 mb-2">
                    <i class="fas fa-arrow-up"></i> Aset per Kelompok
                </h4>
                <ul class="divide-y divide-gray-200">
                    @foreach ($asetData as $kelompok => $data)
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

            <!-- Liabilitas -->
            <div>
                <h4 class="text-lg font-semibold text-red-700 mb-2">
                    <i class="fas fa-arrow-down"></i> Liabilitas per Kelompok
                </h4>
                <ul class="divide-y divide-gray-200">
                    @foreach ($liabilitasData as $kelompok => $data)
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
