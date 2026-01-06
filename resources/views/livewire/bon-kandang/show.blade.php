<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;

new class extends Component {
    public Transaksi $transaksi;
    public Transaksi $hpp;

    public function mount(Transaksi $transaksi): void
    {
        $this->transaksi = $transaksi->load(['client', 'details.barang']);

        $inv = substr($this->transaksi->invoice, -4);
        $part = explode('-', $this->transaksi->invoice);
        $tanggal = $part[1];

        $hpp = Transaksi::where('invoice', 'like', "%-$tanggal-HPP-$inv")->first();
        $this->hpp = $hpp->load(['client', 'details.barang']);

    }
};
?>

<div>
    <x-header title="Detail {{ $transaksi->invoice }}" separator progress-indicator />

    <x-card>

        {{-- Informasi Transaksi --}}
        <div class="p-7 mt-2 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="mb-3">Invoice</p>
                    <p class="font-semibold">{{ $transaksi->invoice }}</p>
                </div>
                <div>
                    <p class="mb-3">User</p>
                    <p class="font-semibold">{{ $transaksi->user?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">Tanggal</p>
                    <p class="font-semibold">{{ \Carbon\Carbon::parse($transaksi->tanggal)->format('d-m-Y H:i') }}</p>
                </div>
                <div>
                    <p class="mb-3">Nama Client</p>
                    <p class="font-semibold">{{ $transaksi->client?->name ?? '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Detail Barang --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <p class="mb-3 font-semibold">Detail Barang</p>

            @forelse ($transaksi->details as $detail)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-3 rounded-lg p-5">
                    <div>
                        <p class="mb-1 text-gray-500">Barang</p>
                        <p class="font-semibold">{{ $detail->barang?->name ?? '-' }} </p>
                    </div>

                    <div>
                        <p class="mb-1 text-gray-500">Qty</p>
                        <p class="font-semibold">{{ $detail->kuantitas }} </p>
                    </div>

                    <div>
                        <p class="mb-1 text-gray-500">Harga</p>
                        <p class="font-semibold">Rp {{ number_format($detail->value, 0, ',', '.') }}</p>
                    </div>

                    <div>
                        <p class="mb-1 text-gray-500">Total</p>
                        <p class="font-semibold">
                            Rp {{ number_format($detail->sub_total, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-sm">Tidak ada detail barang untuk transaksi ini.</p>
            @endforelse
        </div>

        {{-- Total & Pembayaran --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">

            <div class="">
                <p class="mb-1">Grand Total</p>
                <p class="font-semibold text-end text-xl">
                    Rp {{ number_format($transaksi->total, 0, ',', '.') }}
                </p>
            </div>

            <div class="">
                <p class="mb-1">Uang Diterima</p>
                <p class="font-semibold text-end text-xl">
                    Rp {{ number_format($transaksi->uang ?? 0, 0, ',', '.') }}
                </p>
            </div>

            <div>
                <p class="mb-1">Kembalian</p>
                <p class="font-semibold text-end text-xl">
                    Rp {{ number_format($transaksi->kembalian ?? 0, 0, ',', '.') }}
                </p>
            </div>

        </div>
    </x-card>

    <x-header title="Detail {{ $hpp->invoice }}" separator progress-indicator />

    <x-card>

        {{-- Informasi hpp --}}
        <div class="p-7 mt-2 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="mb-3">Invoice</p>
                    <p class="font-semibold">{{ $hpp->invoice }}</p>
                </div>
                <div>
                    <p class="mb-3">User</p>
                    <p class="font-semibold">{{ $hpp->user?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">Tanggal</p>
                    <p class="font-semibold">{{ \Carbon\Carbon::parse($hpp->tanggal)->format('d-m-Y H:i') }}</p>
                </div>
                <div>
                    <p class="mb-3">Nama Client</p>
                    <p class="font-semibold">{{ $hpp->client?->name ?? '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Detail Barang --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <p class="mb-3 font-semibold">Detail Barang</p>

            @forelse ($hpp->details as $detail)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-3 rounded-lg p-5">
                    <div>
                        <p class="mb-1 text-gray-500">Barang</p>
                        <p class="font-semibold">{{ $detail->barang?->name ?? '-' }} </p>
                    </div>

                    <div>
                        <p class="mb-1 text-gray-500">Qty</p>
                        <p class="font-semibold">{{ $detail->kuantitas }} </p>
                    </div>

                    <div>
                        <p class="mb-1 text-gray-500">Harga</p>
                        <p class="font-semibold">Rp {{ number_format($detail->value, 0, ',', '.') }}</p>
                    </div>

                    <div>
                        <p class="mb-1 text-gray-500">Total</p>
                        <p class="font-semibold">
                            Rp {{ number_format($detail->sub_total, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-sm">Tidak ada detail barang untuk hpp ini.</p>
            @endforelse
        </div>

        {{-- Total & Pembayaran --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">

            <div class="">
                <p class="mb-1">Grand Total</p>
                <p class="font-semibold text-end text-xl">
                    Rp {{ number_format($hpp->total, 0, ',', '.') }}
                </p>
            </div>

            <div class="">
                <p class="mb-1">Uang Diterima</p>
                <p class="font-semibold text-end text-xl">
                    Rp {{ number_format($hpp->uang ?? 0, 0, ',', '.') }}
                </p>
            </div>

            <div>
                <p class="mb-1">Kembalian</p>
                <p class="font-semibold text-end text-xl">
                    Rp {{ number_format($hpp->kembalian ?? 0, 0, ',', '.') }}
                </p>
            </div>

        </div>
    </x-card>

    <div class="mt-6 flex gap-3">
        <x-button label="Kembali" link="/bon-kandang" />
    </div>
</div>
