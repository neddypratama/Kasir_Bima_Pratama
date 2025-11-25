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

    public function mount()
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
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
    </div>
</div>
