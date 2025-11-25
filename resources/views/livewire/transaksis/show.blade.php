<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;

new class extends Component {
    public Transaksi $transaksi;
    public $links; // ubah dari single ke koleksi (array/kumpulan transaksi)

    public function mount(Transaksi $transaksi): void
    {
        // Muat transaksi utama lengkap
        $this->transaksi = $transaksi->load(['client', 'details.kategori', 'details.barang']);

        // Ambil suffix terakhir dari invoice (contoh: 3NCP)
        $suffix = substr($this->transaksi->invoice, -4);
        $part = explode('-', $transaksi->invoice);
        $tanggal = $part[1];

        // Ambil SEMUA transaksi lain yang punya suffix sama, tapi bukan transaksi utama
        $this->links = Transaksi::where('invoice', 'like', "INV-$tanggal-%-$suffix")
            ->where('id', '!=', $this->transaksi->id)
            ->with(['client', 'details.kategori', 'details.barang'])
            ->get();
    }
};
?>

<div>
    {{-- Transaksi utama --}}
    <x-header title="Detail {{ $transaksi->invoice }}" separator progress-indicator />

    <x-card>
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
                    <p class="mb-3">Tipe Transaksi</p>
                    <p class="font-semibold">{{ $transaksi->type ?? '-' }}</p>
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
                        <p class="font-semibold">Rp {{ number_format($detail->sub_total, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-gray-500">Kategori</p>
                        <p class="font-semibold">{{ $detail->kategori?->name ?? '-' }}</p>
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

    {{-- Daftar Link Transaksi --}}
    @foreach ($links as $link)
        <x-header title="Detail {{ $link->invoice }}" separator progress-indicator />

        <x-card>
            <div class="p-7 mt-2 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="mb-3">Invoice</p>
                        <p class="font-semibold">{{ $link->invoice }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Rincian Transaksi</p>
                        <p class="font-semibold">{{ $link->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Tanggal</p>
                        <p class="font-semibold">{{ \Carbon\Carbon::parse($link->tanggal)->format('d-m-Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <div class="p-7 mt-4 rounded-lg shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="mb-3">Nama Client</p>
                        <p class="font-semibold">{{ $link->client?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">Tipe Transaksi</p>
                        <p class="font-semibold">{{ $link->type ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-3">User</p>
                        <p class="font-semibold">{{ $link->user?->name ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3 font-semibold">Detail Barang</p>
                @forelse ($link->details as $detail)
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
                            <p class="font-semibold">Rp {{ number_format($detail->sub_total, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-gray-500">Kategori</p>
                            <p class="font-semibold">{{ $detail->kategori?->name ?? '-' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">Tidak ada detail barang untuk transaksi ini.</p>
                @endforelse
            </div>

            <div class="p-7 mt-4 rounded-lg shadow-md">
                <p class="mb-3">Grand Total</p>
                <p class="font-semibold text-end text-yellow-500 text-xl">
                    Rp. {{ number_format($link->total, 0, ',', '.') }}
                </p>
            </div>
        </x-card>
    @endforeach

    <div class="mt-6">
        <x-button label="Kembali" link="/tunai" />
    </div>
</div>
