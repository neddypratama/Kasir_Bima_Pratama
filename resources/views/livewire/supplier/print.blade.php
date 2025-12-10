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
            margin: 0;
            padding: 0;
            font-family: monospace;
        }

        /* === UKURAN KERTAS === */
        @page {
            size: 58mm 210mm;
            /* width 58mm, height 210mm */
            margin: 0;
            /* no margin */
        }

        @media print {
            body {
                width: 58mm;
            }

            .wrapper {
                display: block;
                padding: 0;
                margin: 0;
            }

            .struk {
                width: 58mm;
                padding: 2mm;
            }

            .no-print {
                display: none !important;
            }
        }

        /* === STYLING STRUK UMUM === */
        .wrapper {
            display: flex;
            justify-content: center;
            padding-top: 10px;
        }

        .struk {
            width: 58mm;
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

        .header-logo {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            width: 30px;
            /* kecil agar muat di kertas 58mm */
            height: auto;
        }
    </style>

    <div class="wrapper">
        <div class="struk">

            <!-- LOGO + TITLE -->
            <div class="header-logo">
                <img src="{{ asset('logo.jpeg') }}" class="logo">
                <div class="title">
                    <h3 style="margin:0; font-size:18px;"><strong> BIMA PRATAMA FEED </strong></h3>
                    <p style="margin:0; font-size:13px;"><strong> Dsn Ngiwak Rt1/9 Kel. Candirejo </strong></p>
                    <p style="margin:0; font-size:13px;"><strong> Kec. Ponggok Kab. Blitar </strong></p>
                    <p style="margin:0; font-size:13px;"></strong>WA:</strong>085857609392</p>
                </div>
                <img src="{{ asset('logo.jpeg') }}" class="logo">
            </div>

            <hr>
            <p style="margin:0; font-size:13px;"><strong>Tanggal:</strong> {{ $transaksi->tanggal }}</p>
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
                <span>UANG DIBAYARKAN:</span>
                <span>Rp {{ number_format($transaksi->uang, 0, ',', '.') }}</span>
            </div>

            <div class="total-line">
                <span>KEKURANGAN:</span>
                <span>Rp {{ number_format($transaksi->kembalian, 0, ',', '.') }}</span>
            </div>

            <hr>

            <div class="center" style="margin-top: 10px;">
                <p style="font-size:13px;">"Terimakasih telah melakukan transaksi dengan kami, semoga diberi keberkahan"
                </p>
                <p style="font-size:13px;">~Bima Pratama Feed ~</p>
            </div>

            <div class="center no-print" style="margin-top: 10px;">
                <button onclick="window.print()">Print Struk</button>
                <button onclick="window.history.back()">Kembali</button>
            </div>
        </div>

    </div>

</div>
