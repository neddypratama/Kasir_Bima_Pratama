<?php

use App\Models\Transaksi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.empty')] #[Title('Print Struk')] class extends Component {
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
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: monospace;
        }

        /* HANYA wrapper yang pakai flex */
        .wrapper {
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-top: 20px;
        }

        .struk {
            width: 380px;
            padding: 10px;
        }

        .center {
            text-align: center;
        }

        hr {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .table-header {
            display: flex;
            font-size: 13px;
            justify-content: space-between;
        }

        .item-name {
            width: 40%;
        }

        .item-price {
            width: 20%;
            text-align: right;
        }

        .item-qty {
            width: 15%;
            text-align: right;
        }

        .item-sub {
            width: 25%;
            text-align: right;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 14px;
        }

        @media print {
            .wrapper {
                display: block;
                padding-top: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>

    <div class="wrapper">
        <div class="struk">
            <!-- konten struk tetap sama -->
            <div class="center">
                <h3 style="margin:0; font-size:18px;">KASIR PAKAN HEWAN</h3>
                <p style="margin:0; font-size:13px;">{{ now()->format('d/m/Y H:i') }}</p>
            </div>

            <hr>

            <p style="margin:0; font-size:13px;"><strong>Invoice:</strong> {{ $transaksi->invoice }}</p>
            <p style="margin:0; font-size:13px;"><strong>Client:</strong> {{ $transaksi->client->name ?? '-' }}</p>

            <hr>

            <!-- HEADER -->
            <div class="table-header" style="font-weight: bold;">
                <div style="width:40%">Barang</div>
                <div style="width:20%; text-align:right">Harga</div>
                <div style="width:15%; text-align:right">Qty</div>
                <div style="width:25%; text-align:right">Sub</div>
            </div>

            <hr>

            <!-- ITEMS -->
            @foreach ($transaksi->details as $detail)
                <div class="table-header">
                    <div class="item-name">{{ $detail->barang->name }}</div>
                    <div class="item-price">Rp {{ number_format($detail->value, 0, ',', '.') }}</div>
                    <div class="item-qty">x{{ $detail->kuantitas }}</div>
                    <div class="item-sub">Rp {{ number_format($detail->sub_total, 0, ',', '.') }}</div>
                </div>
            @endforeach

            <hr>

            <!-- TOTAL -->
            <div class="total-line">
                <span>GRAND TOTAL:</span>
                <span>Rp {{ number_format($transaksi->total, 0, ',', '.') }}</span>
            </div>

            <div class="total-line">
                <span>UANG DITERIMA:</span>
                <span>Rp {{ number_format($transaksi->uang, 0, ',', '.') }}</span>
            </div>

            <div class="total-line">
                <span>KEMBALIAN:</span>
                <span>Rp {{ number_format($transaksi->kembalian, 0, ',', '.') }}</span>
            </div>

            <hr>

            <div class="center" style="margin-top: 10px;">
                <p style="font-size:13px;">Terima kasih telah berbelanja!</p>
            </div>

            <div class="center no-print" style="margin-top: 10px;">
                <button onclick="window.print()">Print Struk</button>
                <button onclick="window.history.back()">Kembali</button>
            </div>
        </div>

    </div>
