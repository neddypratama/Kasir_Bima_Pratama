<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\Stok;

new class extends Component {
    public Stok $stok;
    public ?Transaksi $kotor = null;
    public ?Transaksi $pecah = null;
    public $telur = [];

    public function mount(Stok $stok): void
    {
        // Muat relasi stok utama
        $this->stok = $stok->load(['user', 'barang.jenis', 'barang']);
    }
};
?>

<div>
    <x-header title="Detail {{ $stok->invoice }}" separator progress-indicator />

    <x-card>
        {{-- Informasi Transaksi --}}
        <div class="p-7 mt-2 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="mb-3">Invoice</p>
                    <p class="font-semibold">{{ $stok->invoice }}</p>
                </div>
                <div>
                    <p class="mb-3">User</p>
                    <p class="font-semibold">{{ $stok->user?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">Tanggal</p>
                    <p class="font-semibold">{{ \Carbon\Carbon::parse($stok->tanggal)->format('d-m-Y H:i') }}</p>
                </div>
            </div>
        </div>

        {{-- Informasi Client --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="mb-3">Nama Barang</p>
                    <p class="font-semibold">{{ $stok->barang?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">Jenis Barang</p>
                    <p class="font-semibold">{{ $stok->barang->jenis?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">Stok Sebelum</p>
                    <p class="font-semibold">
                        {{ $stok->barang?->stok - $stok->tambah + ($stok->kurang + $stok->kotor + $stok->rusak) ?? 0 }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Detail Barang --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <p class="mb-3 font-semibold">Detail Stok</p>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-3 rounded-lg p-5 ">
                <div>
                    <p class="mb-1 text-gray-500">Tambah</p>
                    <p class="font-semibold">{{ $stok->tambah ?? 0 }}</p>
                </div>
                <div>
                    <p class="mb-1 text-gray-500">Kurang</p>
                    <p class="font-semibold">{{ $stok->kurang ?? 0 }}</p>
                </div>
                <div>
                    <p class="mb-1 text-gray-500">Kotor</p>
                    <p class="font-semibold">{{ $stok->kotor }}</p>
                </div>
                <div>
                    <p class="mb-1 text-gray-500">Pecah</p>
                    <p class="font-semibold">{{ $stok->rusak }}
                    </p>
                </div>
                <div>
                    <p class="mb-1 text-gray-500">Stok Sekarang</p>
                    <p class="font-semibold">{{ $stok->barang?->stok ?? 0 }}</p>
                </div>
            </div>
        </div>
    </x-card>

    <div class="mt-6">
        <x-button label="Kembali" link="/stok" />
    </div>
</div>
