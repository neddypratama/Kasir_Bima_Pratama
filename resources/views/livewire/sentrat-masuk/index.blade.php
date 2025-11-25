<?php

use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Client;
use App\Models\Barang;
use App\Models\User;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\PembelianSentratExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

new class extends Component {
    use Toast;
    use WithPagination;

    public $today;
    public function mount(): void
    {
        $this->today = \Carbon\Carbon::today();
    }

    public string $search = '';
    public bool $drawer = false;
    public array $sortBy = ['column' => 'id', 'direction' => 'desc'];
    public int $filter = 0;
    public int $client_id = 0;

    public bool $exportModal = false; // ✅ Modal export
    // ✅ Tambah tanggal untuk filter export
    public ?string $startDate = null;
    public ?string $endDate = null;

   public $page = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public int $perPage = 25; // Default jumlah data per halaman
    public function clear(): void
    {
        $this->reset(['search', 'client_id', 'filter', 'startDate', 'endDate']);
        $this->resetPage();
        $this->success('Filters cleared.', position: 'toast-top');
    }

    public function openExportModal(): void
    {
        $this->exportModal = true;
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
    }

    public function export(): mixed
    {
        if (!$this->startDate || !$this->endDate) {
            $this->error('Pilih tanggal terlebih dahulu.');
            return null; // ✅ Sekarang tetap return sesuatu
        }

        $this->exportModal = false;
        $this->success('Export dimulai...', position: 'toast-top');

        return Excel::download(new PembelianSentratExport($this->startDate, $this->endDate), 'pembelian-pakan.xlsx');
    }

    public function delete($id): void
    {
        $transaksi = Transaksi::find($id)->load('details');
        if (!$transaksi) {
            $this->error('Transaksi tidak ditemukan.');
            return;
        }

        // 1️⃣ Rollback stok & HPP untuk semua barang dalam transaksi ini
        foreach ($transaksi->details as $oldDetail) {
            $barang = Barang::find($oldDetail->barang_id);
            if (!$barang) {
                continue;
            }

            // Hitung stok baru berdasarkan semua transaksi lain (tanpa transaksi ini)
            $stokDebit = DetailTransaksi::where('barang_id', $barang->id)->where('transaksi_id', '!=', $transaksi->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('type', 'Aset'))->sum('kuantitas');

            $stokKredit = DetailTransaksi::where('barang_id', $barang->id)->where('transaksi_id', '!=', $transaksi->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Kredit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('type', 'Aset'))->sum('kuantitas');

            $stokBaru = $stokDebit - $stokKredit;

            // Hitung ulang HPP dari semua transaksi pembelian lain
            $totalHarga = DetailTransaksi::where('barang_id', $barang->id)->where('transaksi_id', '!=', $transaksi->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('type', 'Aset'))->sum(\DB::raw('value * kuantitas'));

            $totalQty = DetailTransaksi::where('barang_id', $barang->id)->where('transaksi_id', '!=', $transaksi->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('type', 'Aset'))->sum('kuantitas');

            $hppBaru = $totalQty > 0 ? $totalHarga / $totalQty : 0;

            // Jika stok <= 0, reset HPP ke 0
            if ($stokBaru <= 0) {
                $hppBaru = 0;
            }

            $barang->update([
                'stok' => $stokBaru,
                'hpp' => $hppBaru,
            ]);
        }

        $client = Client::find($transaksi->client_id);
        $client->decrement('titipan', (int) $transaksi->total);

        $suffix = substr($transaksi->invoice, -4);
        $hutang = Transaksi::where('invoice', 'like', "%-UTG-$suffix")->first();
        $hutang->details()->delete();
        $hutang->delete();

        // 2️⃣ Hapus detail transaksi
        $transaksi->details()->delete();

        // 3️⃣ Hapus transaksi utama
        $transaksi->delete();

        $this->warning("Transaksi $id dan semua detailnya berhasil dihapus", position: 'toast-top');
    }

    public function headers(): array
    {
        return [['key' => 'invoice', 'label' => 'Invoice', 'class' => 'w-24'], ['key' => 'name', 'label' => 'Rincian', 'class' => 'w-48'], ['key' => 'tanggal', 'label' => 'Tanggal', 'class' => 'w-16'], ['key' => 'client.name', 'label' => 'Client', 'class' => 'w-16'], ['key' => 'total', 'label' => 'Total', 'class' => 'w-24', 'format' => ['currency', 0, 'Rp']]];
    }

    public function transaksi(): LengthAwarePaginator
    {
        return Transaksi::query()
            ->with(['client:id,name', 'details.kategori:id,name,type'])
            ->where('type', 'Debit')
            ->where('invoice', 'like', '%-STR-%')
            ->whereHas('details.kategori', fn(Builder $q) => $q->where('name', 'like', '%Stok Pakan%'))
            ->when($this->search, fn(Builder $q) => $q->where(fn($query) => $query->where('name', 'like', "%{$this->search}%")->orWhere('invoice', 'like', "%{$this->search}%")))
            ->when($this->client_id, fn(Builder $q) => $q->where('client_id', $this->client_id))
            ->when($this->startDate, fn(Builder $q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn(Builder $q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function with(): array
    {
        if ($this->filter >= 0 && $this->filter < 3) {
            $this->filter = 0;
            if (!empty($this->search)) {
                $this->filter++;
            }
            if ($this->client_id != 0) {
                $this->filter++;
            }
            if ($this->startDate != null) {
                $this->filter++;
            }
        }

        return [
            'transaksi' => $this->transaksi(),
            'client' => Client::where('type', 'like', '%Supplier%')
                ->where(function ($q) {
                    $q->where('name', 'like', 'Sentrat%')->orWhere('name', 'like', '%Supriyadi%');
                })
                ->get(),
            'headers' => $this->headers(),
            'perPage' => $this->perPage,
            'pages' => $this->page,
        ];
    }

    public function updated($property): void
    {
        if (!is_array($property) && $property != '') {
            $this->resetPage();
        }
    }
};

?>

<div class="p-4 space-y-6">
    <x-header title="Transaksi Pembelian Pakan" separator progress-indicator>
        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2">
                <x-button wire:click="openExportModal" icon="fas.download" primary>Export Excel</x-button>
                <x-button label="Create" link="/sentrat-masuk/create" responsive icon="o-plus" class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 items-end mb-4">
        <div class="md:col-span-1">
            <x-select label="Show entries" :options="$pages" wire:model.live="perPage" />
        </div>
        <div class="md:col-span-6">
            <x-input placeholder="Cari Invoice..." wire:model.live.debounce="search" clearable
                icon="o-magnifying-glass" />
        </div>
        <div class="md:col-span-1">
            <x-button label="Filters" @click="$wire.drawer = true" responsive icon="o-funnel"
                badge="{{ $this->filter }}" badge-classes="badge-primary" />
        </div>
    </div>

    <!-- TABLE -->
    <x-card class="overflow-x-auto">
        <x-table :headers="$headers" :rows="$transaksi" :sort-by="$sortBy" with-pagination
            link="sentrat-masuk/{id}/show?invoice={invoice}">
            @scope('cell-kategori.name', $transaksi)
                {{ $transaksi->kategori?->name ?? '-' }}
            @endscope

            @scope('actions', $transaksi)
                <div class="flex">
                    @if (Auth::user()->role_id == 1)
                        <x-button icon="o-trash" wire:click="delete({{ $transaksi->id }})"
                            wire:confirm="Yakin ingin menghapus transaksi {{ $transaksi->invoice }} ini?" spinner
                            class="btn-ghost btn-sm text-red-500" />
                    @endif
                    @if (Auth::user()->role_id == 1 ||
                            (Carbon::parse($transaksi->tanggal)->isSameDay($this->today) && $transaksi->user_id == Auth::user()->id))
                        <x-button icon="o-pencil"
                            link="/sentrat-masuk/{{ $transaksi->id }}/edit?invoice={{ $transaksi->invoice }}"
                            class="btn-ghost btn-sm text-yellow-500" />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Filters" right separator with-close-button
        class="w-full sm:w-[90%] md:w-1/2 lg:w-1/3">
        <div class="grid gap-5">
            <x-input placeholder="Cari Invoice..." wire:model.live.debounce="search" clearable
                icon="o-magnifying-glass" />
            <x-choices-offline placeholder="Pilih Client" wire:model.live="client_id" :options="$client"
                option-label="name" option-value="id" icon="o-user" placeholder-value="0" searchable single />

            <!-- ✅ Tambahkan Filter Tanggal -->
            <x-input label="Tanggal Awal" type="date" wire:model.live="startDate" />
            <x-input label="Tanggal Akhir" type="date" wire:model.live="endDate" />

        </div>

        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer=false" />
        </x-slot:actions>
    </x-drawer>

    <!-- ✅ MODAL EXPORT -->
    <x-modal wire:model="exportModal" title="Export Data" separator>
        <div class="grid gap-4">
            <x-input label="Start Date" type="date" wire:model="startDate" />
            <x-input label="End Date" type="date" wire:model="endDate" />
        </div>
        <x-slot:actions>
            <x-button label="Batal" @click="$wire.exportModal=false" />
            <x-button label="Export" class="btn-primary" wire:click="export" spinner />
        </x-slot:actions>
    </x-modal>
</div>
