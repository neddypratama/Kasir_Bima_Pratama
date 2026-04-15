<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;

new class extends Component {
    public ?Transaksi $transaksi;
    public ?Transaksi $bon;
    public ?Transaksi $aset;
    public ?Transaksi $hpp;

    public function mount(Transaksi $transaksi): void
    {
        // dd($transaksi->details());
        $inv = substr($transaksi->invoice, -4);
        $part = explode('-', $transaksi->invoice);
        $tanggal = $part[1];

        $this->transaksi = $transaksi->load(['client', 'details.kategori', 'details.barang']);

        // BON
        $this->bon = Transaksi::where('invoice', 'like', "%$tanggal-BON-$inv")
            ->with(['client', 'user', 'details.kategori', 'details.barang'])
            ->first();

        // ASET
        $this->aset = Transaksi::where('invoice', 'like', "%$tanggal-STR-$inv")
            ->with(['client', 'user', 'details.kategori', 'details.barang'])
            ->first();

        // HPP
        $this->hpp = Transaksi::where('invoice', 'like', "%$tanggal-HPP-$inv")
            ->with(['client', 'user', 'details.kategori', 'details.barang'])
            ->first();
    }
};
?>

<div>
    <x-header title="Detail {{ $transaksi->invoice }}" separator progress-indicator />

    <x-card>
        {{-- Informasi Transaksi --}}
        <div class="p-7 mt-2 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="mb-3">Invoice</p>
                    <p class="font-semibold">{{ $transaksi->invoice }}</p>
                </div>
                <div>
                    <p class="mb-3">Rincian Transaksi</p>
                    <p class="font-semibold">{{ $transaksi->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">Tanggal</p>
                    <p class="font-semibold">{{ \Carbon\Carbon::parse($transaksi->tanggal)->format('d-m-Y H:i') }}</p>
                </div>
            </div>
        </div>

        {{-- Informasi Client --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="mb-3">Nama Client</p>
                    <p class="font-semibold">{{ $transaksi->client?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">Alamat Client</p>
                    <p class="font-semibold">{{ $transaksi->client?->alamat ?? '-' }}</p>
                </div>
                <div>
                    <p class="mb-3">User</p>
                    <p class="font-semibold">{{ $transaksi->user?->name ?? '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Detail Barang --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <p class="mb-3 font-semibold">Detail Barang</p>
            @forelse ($transaksi->details as $detail)
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-3 rounded-lg p-5 ">
                    <div>
                        <p class="mb-1 text-gray-500">Barang</p>
                        <p class="font-semibold">{{ $detail->barang?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-gray-500">Qty</p>
                        <p class="font-semibold">{{ $detail->kuantitas }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-gray-500">Harga</p>
                        <p class="font-semibold">Rp {{ number_format($detail->value, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-gray-500">Total</p>
                        <p class="font-semibold">Rp
                            {{ number_format($detail->value * $detail->kuantitas, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-gray-500">Kategori</p>
                        <p class="font-semibold">{{ $detail->kategori->name ?? '-' }}</p>
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-sm">Tidak ada detail barang untuk transaksi ini.</p>
            @endforelse
        </div>

        {{-- Total --}}
        <div class="p-7 mt-4 rounded-lg shadow-md">
            <p class="mb-3">Grand Total</p>
            <p class="font-semibold text-end text-yellow-500 text-xl">
                Rp. {{ number_format($transaksi->total, 0, ',', '.') }}
            </p>
        </div>
    </x-card>

    @if ($this->bon)
        <x-header title="Detail {{ $bon->invoice }}" separator progress-indicator />

        <x-card>
            {{-- Informasi bon --}}
            <div class="p-7 mt-2 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="mb-3">Invoice</p>
                        <p class="font-semibold">{{ $bon->invoice }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Rincian Transaksi</p>
                        <p class="font-semibold">{{ $bon->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Tanggal</p>
                        <p class="font-semibold">{{ \Carbon\Carbon::parse($bon->tanggal)->format('d-m-Y H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Informasi Client --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="mb-3">Nama Client</p>
                        <p class="font-semibold">{{ $bon->client?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Alamat Client</p>
                        <p class="font-semibold">{{ $bon->client?->alamat ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">User</p>
                        <p class="font-semibold">{{ $bon->user?->name ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Detail Barang --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3 font-semibold">Detail Barang</p>
                @forelse ($bon->details as $detail)
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-3 rounded-lg p-5 ">
                        <div>
                            <p class="mb-1 text-gray-500">Barang</p>
                            <p class="font-semibold">{{ $detail->barang?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Qty</p>
                            <p class="font-semibold">{{ $detail->kuantitas }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Harga</p>
                            <p class="font-semibold">Rp {{ number_format($detail->value, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Total</p>
                            <p class="font-semibold">Rp
                                {{ number_format($detail->value * $detail->kuantitas, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Kategori</p>
                            <p class="font-semibold">{{ $detail->kategori->name ?? '-' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">Tidak ada detail barang untuk bon ini.</p>
                @endforelse
            </div>

            {{-- Total --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3">Grand Total</p>
                <p class="font-semibold text-end text-yellow-500 text-xl">
                    Rp. {{ number_format($bon->total, 0, ',', '.') }}
                </p>
            </div>
        </x-card>
    @endif

    @if ($this->aset)
        <x-header class="mt-3" title="Detail {{ $aset->invoice }}" separator progress-indicator />

        <x-card>
            {{-- Informasi aset --}}
            <div class="p-7 mt-2 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="mb-3">Invoice</p>
                        <p class="font-semibold">{{ $aset->invoice }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Rincian Transaksi</p>
                        <p class="font-semibold">{{ $aset->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Tanggal</p>
                        <p class="font-semibold">{{ \Carbon\Carbon::parse($aset->tanggal)->format('d-m-Y H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Informasi Client --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div>
                        <p class="mb-3">Nama Client</p>
                        <p class="font-semibold">{{ $aset->client?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Alamat Client</p>
                        <p class="font-semibold">{{ $aset->client?->alamat ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">User</p>
                        <p class="font-semibold">{{ $aset->user?->name ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Detail Barang --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3 font-semibold">Detail Barang</p>
                @forelse ($aset->details as $detail)
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-3 rounded-lg p-5 ">
                        <div>
                            <p class="mb-1 text-gray-500">Barang</p>
                            <p class="font-semibold">{{ $detail->barang?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Qty</p>
                            <p class="font-semibold">{{ $detail->kuantitas }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Harga</p>
                            <p class="font-semibold">Rp {{ number_format($detail->value, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Total</p>
                            <p class="font-semibold">Rp
                                {{ number_format($detail->value * $detail->kuantitas, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Kategori</p>
                            <p class="font-semibold">{{ $detail->kategori->name ?? '-' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">Tidak ada detail barang untuk aset ini.</p>
                @endforelse
            </div>

            {{-- Total --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3">Grand Total</p>
                <p class="font-semibold text-end text-yellow-500 text-xl">
                    Rp. {{ number_format($aset->total, 0, ',', '.') }}
                </p>
            </div>
        </x-card>
    @endif
    
    @if ($this->hpp)
        <x-header class="mt-3" title="Detail {{ $hpp->invoice }}" separator progress-indicator />

        <x-card>
            {{-- Informasi hpp --}}
            <div class="p-7 mt-2 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="mb-3">Invoice</p>
                        <p class="font-semibold">{{ $hpp->invoice }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Rincian Transaksi</p>
                        <p class="font-semibold">{{ $hpp->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Tanggal</p>
                        <p class="font-semibold">{{ \Carbon\Carbon::parse($hpp->tanggal)->format('d-m-Y H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Informasi Client --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div>
                        <p class="mb-3">Nama Client</p>
                        <p class="font-semibold">{{ $hpp->client?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Alamat Client</p>
                        <p class="font-semibold">{{ $hpp->client?->alamat ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">User</p>
                        <p class="font-semibold">{{ $hpp->user?->name ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Detail Barang --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3 font-semibold">Detail Barang</p>
                @forelse ($hpp->details as $detail)
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-3 rounded-lg p-5 ">
                        <div>
                            <p class="mb-1 text-gray-500">Barang</p>
                            <p class="font-semibold">{{ $detail->barang?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Qty</p>
                            <p class="font-semibold">{{ $detail->kuantitas }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Harga</p>
                            <p class="font-semibold">Rp {{ number_format($detail->value, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Total</p>
                            <p class="font-semibold">Rp
                                {{ number_format($detail->value * $detail->kuantitas, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Kategori</p>
                            <p class="font-semibold">{{ $detail->kategori->name ?? '-' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">Tidak ada detail barang untuk hpp ini.</p>
                @endforelse
            </div>

            {{-- Total --}}
            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3">Grand Total</p>
                <p class="font-semibold text-end text-yellow-500 text-xl">
                    Rp. {{ number_format($hpp->total, 0, ',', '.') }}
                </p>
            </div>
        </x-card>
    @endif

    <div class="mt-6">
        <x-button label="Kembali" link="/telur-return" />
    </div>
</div>
