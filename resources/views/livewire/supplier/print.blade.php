<?php

use App\Models\Transaksi;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.empty')] class extends Component {
    public Transaksi $transaksi;

    public function mount(Transaksi $transaksi)
    {
        $this->transaksi = $transaksi->load(['client', 'details.barang']);
    }
};
?>

<div>
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            font-family: monospace;
        }

        .struk {
            width: 280px;
            padding: 10px;
            margin: 0 auto;
        }

        .center {
            text-align: center;
        }

        .row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }

        hr {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
    </style>

    <div class="struk">
        <div class="center">
            <h3 style="margin:0;">PEMBELIAN - TOKO PAKAN</h3>
            <p style="margin:0;">{{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <hr>

        <div><strong>Invoice:</strong> {{ $transaksi->invoice }}</div>
        <div><strong>Supplier:</strong> {{ $transaksi->client->name ?? '-' }}</div>

        <hr>

        @foreach ($transaksi->details as $d)
            <div class="row">
                <div style="width:45%">{{ $d->barang->name }}</div>
                <div style="width:15%; text-align:right">x{{ $d->kuantitas }}</div>
                <div style="width:30%; text-align:right">Rp {{ number_format($d->sub_total, 0, ',', '.') }}</div>
            </div>
        @endforeach

        <hr>

        <div class="row"><strong>Total</strong><strong>Rp {{ number_format($transaksi->total, 0, ',', '.') }}</strong>
        </div>
        <div class="row"><span>Bayar</span><span>Rp {{ number_format($transaksi->uang, 0, ',', '.') }}</span></div>
        <div class="row"><span>Kekurangan</span><span>Rp
                {{ number_format($transaksi->kekurangan, 0, ',', '.') }}</span>
        </div>
        <div class="row"><span>Status</span><span>{{ $transaksi->status }}</span></div>

        <hr>
        <div class="center"><small>Terima kasih</small></div>
        <div class="center no-print" style="margin-top:8px;">
            <button onclick="window.print()">Print</button>
            <button onclick="window.history.back()">Kembali</button>
        </div>
    </div>
</div>
