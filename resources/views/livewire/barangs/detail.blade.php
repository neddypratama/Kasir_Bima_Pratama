<?php

use Livewire\Volt\Component;
use App\Models\Barang;

new class extends Component {
    public Barang $barang;

    public function mount(Barang $barang): void
    {
        $this->barang = $barang->load([
            'jenis',
            'satuans', // relasi konversi satuan
        ]);
    }
};
?>

<div>
    <x-header title="Detail Barang - {{ $barang->name }}" separator progress-indicator />

    <x-card>

        {{-- ===================== INFORMASI BARANG ===================== --}}
        <div class="p-7 mt-2 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">

                <div>
                    <p class="mb-3">Nama Barang</p>
                    <p class="font-semibold">{{ $barang->name }}</p>
                </div>

                <div>
                    <p class="mb-3">Jenis Barang</p>
                    <p class="font-semibold">{{ $barang->jenis?->name ?? '-' }}</p>
                </div>

                <div>
                    <p class="mb-3">Stok</p>
                    <p class="font-semibold">
                        {{ $barang->stok }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ===================== DETAIL KONVERSI SATUAN ===================== --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <p class="mb-3 font-semibold">Detail Barang</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3 rounded-lg p-5">

                <div>
                    <p class="mb-3">HPP</p>
                    <p class="font-semibold">
                        Rp {{ number_format($barang->hpp ?? 0, 0, ',', '.') }}
                    </p>
                </div>

                <div>
                    <p class="mb-1 text-gray-500">Harga Eceran</p>
                    <p class="font-semibold">
                        Rp {{ number_format($barang->harga_eceran, 0, ',', '.') }}
                    </p>
                </div>

                <div>
                    <p class="mb-1 text-gray-500">Harga Sak</p>
                    <p class="font-semibold">
                        Rp {{ number_format($barang->harga_sak, 0, ',', '.') }}
                    </p>
                </div>

            </div>
            @empty
                <p class="text-gray-500 text-sm">Belum ada konversi satuan untuk barang ini.</p>
                @endforelse
            </div>

        </x-card>

        {{-- ===================== ACTION ===================== --}}
        <div class="mt-6 flex gap-3">
            <x-button label="Kembali" link="/barangs" />
            <x-button label="Edit Barang" icon="o-pencil" class="btn-primary" link="/barangs/{{ $barang->id }}/edit" />
        </div>
    </div>
