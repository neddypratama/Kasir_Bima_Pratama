<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\StokBatch;

new class extends Component {
    use Toast, WithPagination;

    public string $search = '';
    public string $startDate = '';
    public string $endDate = '';
    public int $perPage = 25;

    public array $pages = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100']];

    public array $sortBy = [
        'column' => 'nama_barang',
        'direction' => 'asc',
    ];

    /* =========================
     | HEADER
     ========================= */
    public function headers(): array
    {
        return [['key' => 'nama_barang', 'label' => 'Nama Barang'], ['key' => 'stok_masuk', 'label' => 'Stok Masuk'], ['key' => 'stok_terjual', 'label' => 'Stok Terjual'], ['key' => 'stok_sekarang', 'label' => 'Stok Transaksi'], ['key' => 'stok_barang', 'label' => 'Stok Real'], ['key' => 'status', 'label' => 'Status'], ['key' => 'aksi', 'label' => 'Aksi']];
    }

    /* =========================
     | LAPORAN STOK
     ========================= */
    public function laporan(): LengthAwarePaginator
    {
        return DB::table('barangs as b')
            ->select(
                'b.id as id',
                'b.name as nama_barang',
                'b.stok as stok_barang',

                DB::raw("
                    COALESCE(SUM(CASE WHEN k.name LIKE 'Stok%' THEN dt.kuantitas END),0)
                    AS stok_masuk
                "),

                DB::raw("
                    COALESCE(SUM(CASE WHEN k.name LIKE 'Penjualan%' THEN dt.kuantitas END),0)
                    AS stok_terjual
                "),

                DB::raw("
                    (
                        COALESCE(SUM(CASE WHEN k.name LIKE 'Stok%' THEN dt.kuantitas END),0)
                        -
                        COALESCE(SUM(CASE WHEN k.name LIKE 'Penjualan%' THEN dt.kuantitas END),0)
                    ) AS stok_sekarang
                "),
            )
            ->leftJoin('detail_transaksis as dt', 'b.id', '=', 'dt.barang_id')
            ->leftJoin('transaksis as t', 'dt.transaksi_id', '=', 't.id')
            ->leftJoin('kategoris as k', 'k.id', '=', 'dt.kategori_id')
            ->when($this->startDate, fn($q) => $q->whereDate('t.tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('t.tanggal', '<=', $this->endDate))
            ->when($this->search, fn($q) => $q->where('b.name', 'like', "%{$this->search}%"))
            ->groupBy('b.id', 'b.name', 'b.stok')
            ->orderBy('b.name')
            ->paginate($this->perPage);
    }

    /* =========================
     | PERBAIKI 1 BARANG
     ========================= */
    public function perbaikiStok(int $barangId): void
    {
        DB::transaction(function () use ($barangId) {
            $barang = Barang::lockForUpdate()->findOrFail($barangId);

            $row = collect($this->laporan()->getCollection())->firstWhere('id', $barangId);

            if (!$row) {
                return;
            }

            $selisih = $barang->stok - $row->stok_sekarang;

            if ($selisih == 0) {
                return;
            }

            $this->buatTransaksiKoreksi($barang, $selisih);
        });

        $this->success('Stok berhasil diperbaiki');
    }

    /* =========================
     | PERBAIKI SEMUA
     ========================= */
    public function perbaikiSemua(): void
    {
        DB::transaction(function () {
            $rows = $this->laporan()->getCollection();

            foreach ($rows as $row) {
                $barang = Barang::find($row->id);

                $selisih = $row->stok_barang - $row->stok_sekarang;

                if ($selisih == 0) {
                    continue;
                }

                $this->buatTransaksiKoreksi($barang, $selisih);
            }
        });

        $this->success('Semua stok berhasil diperbaiki');
    }

    /* =========================
     | HELPER KOREKSI (FINAL)
     ========================= */
    private function buatTransaksiKoreksi(Barang $barang, float $qty): void
    {
        // dd($barang, $qty);
        $invoice = 'INV-' . now()->format('Ymd') . '-STK-' . Str::upper(Str::random(4));

        $harga = DetailTransaksi::where('barang_id', $barang->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Stok'))->join('transaksis as t', 't.id', '=', 'detail_transaksis.transaksi_id')->orderByDesc('t.tanggal')->orderByDesc('detail_transaksis.id')->value('detail_transaksis.value') ?? $barang->hpp;

        $kategori = Kategori::where('name', 'like', 'Stok %' . $barang->jenis->name)->firstOrFail();

        if ($qty > 0) {
            $transaksi = Transaksi::create([
                'invoice' => $invoice,
                'user_id' => auth()->id(),
                'tanggal' => now(),
                'type' => 'Stok',
                'total' => $harga * $qty,
                'status' => 'Lunas',
                'uang' => $harga * $qty,
                'bayar' => 'Cash',
                'kembalian' => 0,
            ]);

            $detail = DetailTransaksi::create([
                'transaksi_id' => $transaksi->id,
                'barang_id' => $barang->id,
                'kategori_id' => $kategori->id,
                'value' => $harga,
                'kuantitas' => $qty,
                'sub_total' => $harga * $qty,
            ]);

            StokBatch::create([
                'barang_id' => $barang->id,
                'user_id' => auth()->id(),
                'detail_transaksi_id' => $detail->id,
                'qty_masuk' => $qty,
                'qty_sisa' => $qty,
                'harga' => $harga,
                'tanggal' => now()->format('Y-m-d\TH:i:s'),
            ]);
        } else {
            $barang->increment('stok', abs($qty));
        }
    }

    public function with(): array
    {
        return [
            'rows' => $this->laporan(),
            'headers' => $this->headers(),
            'pages' => $this->pages,
        ];
    }

    public function updated(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-header title="Audit & Koreksi Stok" separator />

    <div class="grid grid-cols-1 md:grid-cols-5 gap-2 mb-4">
        <x-select label="Show" :options="$pages" wire:model.live="perPage" />
        <x-input type="date" label="Tanggal Awal" wire:model.live="startDate" />
        <x-input type="date" label="Tanggal Akhir" wire:model.live="endDate" />
        <x-input label="Cari Barang" wire:model.live.debounce="search" />

        <x-button label="Perbaiki Semua" icon="o-wrench-screwdriver" class="btn-error" wire:click="perbaikiSemua"
            wire:confirm="Yakin perbaiki SEMUA stok yang tidak benar?" spinner />
    </div>

    <x-card>
        <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy" with-pagination>

            @scope('cell_status', $row)
                @php
                    $selisih = $row->stok_barang - $row->stok_sekarang;
                @endphp

                @if ($selisih == 0)
                    <span class="badge badge-success">Benar</span>
                @else
                    <span class="badge badge-error">Tidak Benar</span>
                @endif
            @endscope

            @scope('cell_aksi', $row)
                @php
                    $selisih = $row->stok_barang - $row->stok_sekarang;
                @endphp

                @if ($selisih != 0)
                    <x-button label="Perbaiki" icon="o-wrench" class="btn-error btn-sm"
                        wire:click="perbaikiStok({{ $row->id }})" wire:confirm="Perbaiki stok {{ $row->nama_barang }}?"
                        spinner />
                @else
                    —
                @endif
            @endscope

        </x-table>
    </x-card>
</div>
