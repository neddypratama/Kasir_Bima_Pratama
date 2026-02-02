<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

new class extends Component {
    use Toast, WithPagination;

    public string $search = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $filterType = 'Semua';

    public int $perPage = 25;

    public array $pages = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public array $types = [['id' => 'Semua', 'name' => 'Semua'], ['id' => 'Stok', 'name' => 'Pembelian'], ['id' => 'Kredit', 'name' => 'Penjualan'], ['id' => 'Sisa', 'name' => 'Sisa Stok']];

    public function clear(): void
    {
        $this->reset(['search', 'startDate', 'endDate', 'filterType']);
        $this->resetPage();
        $this->success('Filter dibersihkan');
    }

    public array $sortBy = [
        'column' => 'nama_barang',
        'direction' => 'asc',
    ];

    public function headers(): array
    {
        return [['key' => 'nama_barang', 'label' => 'Nama Barang'], ['key' => 'total_pembelian', 'label' => 'Total Pembelian'], ['key' => 'harga_beli', 'label' => 'Total Harga Pembelian (Rp)'], ['key' => 'total_penjualan', 'label' => 'Total Penjualan'], ['key' => 'total_harga', 'label' => 'Total Harga Penjualan (Rp)'], ['key' => 'sisa_stok', 'label' => 'Sisa Stok']];
    }

    public function laporan(): LengthAwarePaginator
    {
        return DB::table('barangs as barang')
            ->select(
                'barang.name as nama_barang',

                // TOTAL PEMBELIAN
                DB::raw("
                    SUM(
                        CASE 
                            WHEN kategori.name LIKE '%Stok%' 
                            THEN detail_transaksis.kuantitas 
                            ELSE 0 
                        END
                    ) as total_pembelian
                "),

                // TOTAL PENJUALAN
                DB::raw("
                    SUM(
                        CASE 
                            WHEN kategori.name LIKE '%Penjualan%' 
                            THEN detail_transaksis.kuantitas 
                            ELSE 0 
                        END
                    ) as total_penjualan
                "),

                // TOTAL HARGA PEMBELIAN
                DB::raw("
                    SUM(
                        CASE 
                            WHEN kategori.name LIKE '%Stok%' 
                            THEN detail_transaksis.kuantitas * detail_transaksis.value 
                            ELSE 0 
                        END
                    ) as harga_beli
                "),

                // TOTAL HARGA PENJUALAN
                DB::raw("
                    SUM(
                        CASE 
                            WHEN kategori.name LIKE '%Penjualan%' 
                            THEN detail_transaksis.kuantitas * detail_transaksis.value 
                            ELSE 0 
                        END
                    ) as total_harga
                "),

                // SISA STOK
                DB::raw("
                    (
                        SUM(
                            CASE 
                                WHEN kategori.name LIKE '%Stok%' 
                                THEN detail_transaksis.kuantitas 
                                ELSE 0 
                            END
                        )
                        -
                        SUM(
                            CASE 
                                WHEN kategori.name LIKE '%Penjualan%' 
                                THEN detail_transaksis.kuantitas 
                                ELSE 0 
                            END
                        )
                    ) as sisa_stok
                "),
            )
            ->leftJoin('detail_transaksis', 'barang.id', '=', 'detail_transaksis.barang_id')
            ->leftJoin('transaksis as transaksi', 'detail_transaksis.transaksi_id', '=', 'transaksi.id')
            ->leftJoin('kategoris as kategori', 'kategori.id', '=', 'detail_transaksis.kategori_id')

            // FILTER TIPE
            ->when($this->filterType === 'Stok', fn($q) => $q->where('kategori.name', 'like', '%Stok%'))
            ->when($this->filterType === 'Kredit', fn($q) => $q->where('kategori.name', 'like', '%Penjualan%'))

            // FILTER TANGGAL
            ->when($this->startDate, fn($q) => $q->whereDate('transaksi.tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('transaksi.tanggal', '<=', $this->endDate))

            // SEARCH
            ->when($this->search, fn($q) => $q->where('barang.name', 'like', "%{$this->search}%"))

            ->groupBy('barang.name')
            ->orderBy(
                match ($this->sortBy['column']) {
                    'nama_barang' => 'barang.name',
                    'total_pembelian' => 'total_pembelian',
                    'total_penjualan' => 'total_penjualan',
                    'harga_beli' => 'harga_beli',
                    'total_harga' => 'total_harga',
                    'sisa_stok' => 'sisa_stok',
                    default => 'barang.name',
                },
                $this->sortBy['direction'],
            )
            ->paginate($this->perPage);
    }

    public function with(): array
    {
        return [
            'rows' => $this->laporan(),
            'headers' => $this->headers(),
            'pages' => $this->pages,
            'types' => $this->types,
        ];
    }

    public function updated(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <x-header title="Laporan Stok & Penjualan" separator />

    <div class="grid grid-cols-1 md:grid-cols-10 gap-2   mb-4">
        <div class="md:col-span-1">
            <x-select label="Show" :options="$pages" wire:model.live="perPage" />
        </div>

        <div class="md:col-span-2">
            <x-input type="date" label="Tanggal Awal" wire:model.live="startDate" />
        </div>

        <div class="md:col-span-2">
            <x-input type="date" label="Tanggal Akhir" wire:model.live="endDate" />
        </div>

        <div class="md:col-span-2">
            <x-select label="Tipe" :options="$types" wire:model.live="filterType" />
        </div>

        <div class="md:col-span-3">
            <x-input placeholder="Cari barang..." wire:model.live.debounce="search" label="Cari" />
        </div>
    </div>

    <x-card>
        <x-card>
            <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy" with-pagination>
                @scope('cell_sisa_stok', $row)
                    <span class="font-bold {{ $row->sisa_stok < 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format($row->sisa_stok, 2, ',', '.') }}
                    </span>
                @endscope

                @scope('cell_harga_beli', $row)
                    Rp {{ number_format($row->harga_beli, 0, ',', '.') }}
                @endscope

                @scope('cell_total_harga', $row)
                    Rp {{ number_format($row->total_harga, 0, ',', '.') }}
                @endscope
            </x-table>
        </x-card>

    </x-card>
</div>
