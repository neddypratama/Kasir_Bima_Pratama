<?php

use App\Models\Transaksi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.empty')] #[Title('Print Struk')] class extends Component {
    public Transaksi $transaksi;

    public function mount(Transaksi $transaksi)
    {
        $this->transaksi = $transaksi->load(['client', 'details.barang', 'details.satuan']);
    }
};
?>

<div>
    <style>
        /* RESET */
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: monospace;
            font-size: 10.5px;
            background: #fff;
            color: #000;
        }

        /* KERTAS THERMAL 58mm */
        @page {
            size: 58mm auto;
            margin: 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }
        }

        /* AREA CETAK AMAN (ANTI KEPOTONG) */
        .paper {
            width: 58mm;

            /* PENTING: KANAN LEBIH BESAR */
            padding: 6px 50px 6px 2px;

            margin: 0 auto;
        }

        /* ALIGN */
        .center {
            text-align: center;
        }

        .left {
            text-align: left;
        }

        /* GARIS */
        .line {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        /* ITEM */
        .item {
            margin-bottom: 5px;
        }

        .item-name {
            white-space: normal;
            word-break: break-word;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
        }

        .item-row span {
            white-space: nowrap;
        }

        .item-row span:last-child {
            min-width: 40px;
            text-align: right;
        }

        /* TOTAL */
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            font-size: 10px;
            line-height: 1.4;
        }

        .footer p {
            margin: 0;
        }
    </style>

    <div class="paper">

        <!-- HEADER -->
        <div class="center">
            <img src="{{ asset('logo.jpeg') }}" width="32" alt="logo">
            <div><strong>BIMA PRATAMA FEED</strong></div>
            <div>Dsn Ngiwak Rt1/9 Kel. Candirejo</div>
            <div>Kec. Ponggok Kab. Blitar</div>
            <div>WA: 085857609392</div>
        </div>

        <div class="line"></div>

        <!-- INFO -->
        <div class="left">
            <div>Tanggal : {{ $transaksi->tanggal }}</div>
            <div>Invoice : {{ $transaksi->invoice }}</div>
            <div>Client : {{ $transaksi->client->name ?? 'Guest' }}</div>
        </div>

        <div class="line"></div>

        <!-- ITEMS -->
        @foreach ($transaksi->details as $detail)
            <div class="item">
                <div class="item-name">
                    {{ strtoupper($detail->barang->name) }} ({{ $detail->satuan->name }})
                </div>
                <div class="item-row">
                    <span>
                        {{ $detail->kuantitas }} x {{ number_format($detail->value, 0, ',', '.') }}
                    </span>
                    <span>
                        {{ number_format($detail->sub_total, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        @endforeach

        <div class="line"></div>

        <!-- TOTAL -->
        <div class="total-row">
            <span>TOTAL</span>
            <span>{{ number_format($transaksi->total, 0, ',', '.') }}</span>
        </div>
        <div class="item-row">
            <span>BAYAR</span>
            <span>{{ number_format($transaksi->uang, 0, ',', '.') }}</span>
        </div>
        <div class="item-row">
            <span>KEKURANGAN</span>
            <span>{{ number_format($transaksi->kembalian, 0, ',', '.') }}</span>
        </div>

        <div class="line"></div>

        <!-- FOOTER -->
        <div class="footer">
            <p>"Terimakasih telah melakukan, transaksi dengan kami, Semoga diberi keberkahan"</p>
        </div>
        <p class="text-center mt-3">~ Bima Pratama Feed ~</p>    

        <!-- BUTTON -->
        <div class="center no-print" style="margin-top:8px;">
            <button onclick="window.print()">Print</button>
            <button onclick="history.back()">Kembali</button>
        </div>

    </div>
</div>
