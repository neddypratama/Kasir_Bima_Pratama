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
            font-size: 11px;
        }

        /* === UKURAN KERTAS 58 x 210 MM === */
        @page {
            size: 58mm 210mm;
            margin: 0;
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
                padding: 2mm 2mm;
            }

            .no-print {
                display: none !important;
            }
        }

        /* WRAPPER */
        .wrapper {
            display: flex;
            justify-content: center;
            padding-top: 6px;
        }

        .struk {
            width: 58mm;
            padding: 6px 6px;
            box-sizing: border-box;
        }

        .center {
            text-align: center;
        }

        hr {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        /* HEADER */
        .header-logo {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-title {
            flex: 1 1 auto;
            text-align: center;
        }

        .header-title h3 {
            font-size: 14px;
            margin: 0;
            line-height: 1;
        }

        .header-title p {
            font-size: 10px;
            margin: 0;
            line-height: 1;
        }

        .logo {
            width: 28px;
            height: auto;
        }

        /* TABEL (HEADER + ROWS) */
        /* Gunakan kelas 'table' sebagai kontainer; header dan rows berbagi aturan kolom */
        table {
            width: 100%;
            display: block;
            box-sizing: border-box;
        }

        .table-row {
            display: flex;
            width: 100%;
            align-items: flex-start;
            box-sizing: border-box;
        }

        /* Kolom presisi agar tidak overflow */
        .col-name {
            width: 35%;
            font-size: 9px;
            word-wrap: break-word;
            white-space: normal;
        }

        .col-price {
            width: 18%;
            font-size: 9px;
            text-align: right;
            white-space: nowrap;
        }

        .col-qty {
            width: 15%;
            font-size: 9px;
            text-align: right;
            white-space: nowrap;
        }

        .col-sub {
            width: 27%;
            font-size: 9px;
            text-align: right;
            white-space: nowrap;
        }

        .table-header {
            font-weight: bold;
            font-size: 9px;
        }

        .table-item {
            margin: 2px 0;
        }

        /* Jika nama barang sangat panjang, ini memastikan text-wrap */
        .col-name span {
            display: inline-block;
            word-break: break-word;
            white-space: normal;
        }

        /* TOTAL */
        .total-line {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: bold;
            margin-top: 6px;
        }

        .footer-text {
            text-align: center;
            font-size: 10px;
            margin-top: 8px;
        }

        /* Tombol (non-print) */
        .no-print button {
            margin: 4px;
            padding: 6px 8px;
            font-size: 12px;
        }
    </style>

    <div class="wrapper">
        <div class="struk">

            <!-- HEADER -->
            <div class="header-logo">
                <img src="{{ asset('logo.jpeg') }}" class="logo" alt="logo">
                <div class="header-title">
                    <h3><strong>BIMA PRATAMA FEED</strong></h3>
                    <p><strong>Dsn Ngiwak Rt1/9 Kel. Candirejo</strong></p>
                    <p><strong>Kec. Ponggok Kab. Blitar</strong></p>
                    <p><strong>WA: 085857609392</strong></p>
                </div>
                <img src="{{ asset('logo.jpeg') }}" class="logo" alt="logo">
            </div>

            <hr>

            <!-- INFO TRANSAKSI -->
            <p style="margin:0; font-size:11px;"><strong>Tanggal:</strong> {{ $transaksi->tanggal }}</p>
            <p style="margin:0; font-size:11px;"><strong>Invoice:</strong> {{ $transaksi->invoice }}</p>
            <p style="margin:0; font-size:11px;"><strong>Client:</strong> {{ $transaksi->client->name ?? '-' }}</p>

            <hr>

            <!-- HEADER TABEL -->
            <div class="table table-header table-row" role="heading">
                <div class="col-name">Barang</div>
                <div class="col-price">Harga</div>
                <div class="col-qty">Qty</div>
                <div class="col-sub">Sub</div>
            </div>

            <hr>

            <!-- DETAIL ITEMS -->
            <div class="table">
                @foreach ($transaksi->details as $detail)
                    <div class="table-row table-item" aria-label="item">
                        <!-- Nama barang: boleh wrap -->
                        <div class="col-name"><span>{{ $detail->barang->name }}</span></div>

                        <!-- Harga: jangan wrap -->
                        <div class="col-price">Rp{{ number_format($detail->value, 0, ',', '.') }}</div>

                        <!-- Qty -->
                        <div class="col-qty">x{{ (int) $detail->kuantitas }}</div>

                        <!-- Sub total -->
                        <div class="col-sub">Rp{{ number_format($detail->sub_total, 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>

            <hr>

            <!-- TOTAL -->
            <div class="total-line">
                <span>GRAND TOTAL:</span>
                <span>Rp{{ number_format($transaksi->total, 0, ',', '.') }}</span>
            </div>

            <div class="total-line">
                <span>UANG DITERIMA:</span>
                <span>Rp{{ number_format($transaksi->uang, 0, ',', '.') }}</span>
            </div>

            <div class="total-line">
                <span>KEMBALIAN:</span>
                <span>Rp{{ number_format($transaksi->kembalian, 0, ',', '.') }}</span>
            </div>

            <hr>

            <!-- FOOTER -->
            <div class="footer-text">
                <p>"Terimakasih telah melakukan transaksi dengan kami, semoga diberi keberkahan"</p>
                <p>~ Bima Pratama Feed ~</p>
            </div>

            <div class="center no-print" style="margin-top: 10px;">
                <button onclick="window.print()">Print Struk</button>
                <button onclick="window.history.back()">Kembali</button>
            </div>

        </div>
    </div>

</div>
