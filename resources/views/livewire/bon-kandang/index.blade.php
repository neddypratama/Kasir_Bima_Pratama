<?php

use App\Models\Transaksi;
use App\Models\TransaksiLink;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\Client;
use App\Models\User;
use App\Models\Kategori;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\PenjualanSentratExport;
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
    public int $barang_id = 0;

    public bool $exportModal = false; // ✅ Modal export
    // ✅ Tambah tanggal untuk filter export
    public ?string $startDate = null;
    public ?string $endDate = null;

    public bool $statusModal = false;
    public ?int $selectedId = null;
    public ?string $selectedInv = null;
    public ?string $newStatus = null;

    public function openStatusModal($id): void
    {
        $this->selectedId = $id;
        $this->selectedInv = Transaksi::find($id)->invoice ?? null;
        $this->newStatus = Transaksi::find($id)->status ?? 'Hutang';
        $this->statusModal = true;
    }

    public function updateStatus(): void
    {
        $transaksi = Transaksi::findOrFail($this->selectedId);
        $hpp = Transaksi::where('invoice', 'like', '%-HPP-' . substr($transaksi->invoice, -4))->first();
        if ($this->newStatus == 'Lunas') {
            $transaksi->update([
                'status' => $this->newStatus,
                'kembalian' => 0,
                'uang' => $transaksi->total,
                'updated_at' => now(),
            ]);
            $hpp->update([
                'status' => $this->newStatus,
                'updated_at' => now(),
            ]);
        } else {
            $transaksi->update([
                'status' => $this->newStatus,
                'updated_at' => now(),
            ]);
            $hpp->update([
                'status' => $this->newStatus,
                'updated_at' => now(),
            ]);
        }

        $this->statusModal = false;
        $this->success("Status transaksi {$transaksi->invoice} berhasil diubah menjadi {$this->newStatus}", position: 'toast-top');
    }

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

        return Excel::download(new PenjualanSentratExport($this->startDate, $this->endDate), 'penjualan-pakan.xlsx');
    }

    public function delete($id): void
    {
        $transaksi = Transaksi::findOrFail($id);

        $inv = substr($transaksi->invoice, -4);
        $part = explode('-', $transaksi->invoice);
        $tanggal = $part[1];

        $hpp = Transaksi::where('invoice', 'like', "%-$tanggal-HPP-$inv")->first();
        $hpp->details()->delete();
        $hpp->delete();

        // 🔄 KEMBALIKAN STOK BARANG
        foreach ($transaksi->details as $detail) {
            $barang = Barang::find($detail->barang_id);

            if ($barang) {
                $barang->increment('stok', $detail->kuantitas);
            }
        }

        // 🗑 Hapus detail transaksi
        $transaksi->details()->delete();

        // 🗑 Hapus transaksi utama
        $transaksi->delete();

        $this->warning("Transaksi {$transaksi->invoice} & semua detail berhasil dihapus.", position: 'toast-top');
    }

    public function headers(): array
    {
        return [['key' => 'invoice', 'label' => 'Invoice', 'class' => 'w-36'], ['key' => 'tanggal', 'label' => 'Tanggal', 'class' => 'w-16'], ['key' => 'client.name', 'label' => 'Client', 'class' => 'w-16'], ['key' => 'bayar', 'label' => 'Metode', 'class' => 'w-16'], ['key' => 'total', 'label' => 'Total', 'class' => 'w-24', 'format' => ['currency', 0, 'Rp']], ['key' => 'status', 'label' => 'Status', 'class' => 'w-16']];
    }

    public function transaksi(): LengthAwarePaginator
    {
        return Transaksi::query()
            ->with(['client:id,name,keterangan', 'details.barang:id,name'])
            ->where('type', 'Kredit')
            ->where('status', 'Hutang')
            ->when($this->barang_id, function (Builder $q) {
                $q->whereHas('clients', function ($q2) {
                    $q2->where('name', 'like', 'Kandang Kambing%');
                });
            })

            // 🔍 SEARCH INVOICE
            ->when($this->search, function (Builder $q) {
                $q->where('invoice', 'like', "%{$this->search}%");
            })

            // 📦 FILTER BARANG (BENAR)
            ->when($this->barang_id, function (Builder $q) {
                $q->whereHas('details', function ($q2) {
                    $q2->where('barang_id', $this->barang_id);
                });
            })

            // 👤 FILTER CLIENT
            ->when($this->client_id, fn(Builder $q) => $q->where('client_id', $this->client_id))

            // 📅 FILTER TANGGAL
            ->when($this->startDate, fn(Builder $q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn(Builder $q) => $q->whereDate('tanggal', '<=', $this->endDate))

            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function with(): array
    {
        if ($this->filter >= 0 && $this->filter < 4) {
            $this->filter = 0;
            if (!empty($this->search)) {
                $this->filter++;
            }
            if ($this->client_id != 0) {
                $this->filter++;
            }
            if ($this->barang_id != 0) {
                $this->filter++;
            }
            if ($this->startDate != null) {
                $this->filter++;
            }
        }

        return [
            'transaksi' => $this->transaksi(),
            'barang' => Barang::all(),
            'client' => Client::where('keterangan', 'Pembeli')->get(),
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
    <x-header title="Transaksi Piutang Kandang" separator progress-indicator>
        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2">
                <x-button wire:click="openExportModal" icon="fas.download" primary>Export Excel</x-button>
                <x-button label="Create" link="/bon-kandang/create" responsive icon="o-plus" class="btn-primary" />
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

    <x-card class="overflow-x-auto">
        <x-table :headers="$headers" :rows="$transaksi" :sort-by="$sortBy" with-pagination
            link="bon-kandang/{id}/show?invoice={invoice}">
            @scope('cell_status', $transaksi)
                @if ($transaksi->status == 'Lunas')
                    <span class="badge badge-success">{{ $transaksi->status }}</span>
                @elseif ($transaksi->status == 'Hutang')
                    <span class="badge badge-error">{{ $transaksi->status }}</span>
                @else
                    <span class="badge badge-ghost">{{ $transaksi->status }}</span>
                @endif
            @endscope
            @scope('actions', $transaksi)
                <div class="flex">
                    @if (Auth::user()->role_id == 1)
                        <x-button icon="o-trash" wire:click="delete({{ $transaksi->id }})"
                            wire:confirm="Yakin ingin menghapus transaksi {{ $transaksi->invoice }} ini?" spinner
                            class="btn-ghost btn-sm text-red-500" tooltip="Delete" />
                        <x-button icon="o-pencil-square" wire:click="openStatusModal({{ $transaksi->id }})"
                            class="btn-ghost btn-sm text-purple-500" tooltip="Update Status" />
                    @endif
                    @if (Auth::user()->role_id == 1 ||
                            (Carbon::parse($transaksi->tanggal)->isSameDay($this->today) && $transaksi->user_id == Auth::user()->id))
                        <x-button icon="o-pencil" link="/bon-kandang/{{ $transaksi->id }}/edit?invoice={{ $transaksi->invoice }}"
                            class="btn-ghost btn-sm text-yellow-500" tooltip="Edit" />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawer" title="Filters" right separator with-close-button
        class="w-full sm:w-[90%] md:w-1/2 lg:w-1/3">
        <div class="grid gap-5">
            <x-input placeholder="Cari Invoice..." wire:model.live.debounce="search" clearable
                icon="o-magnifying-glass" />

            <x-choices-offline placeholder="Pilih Client" wire:model.live="client_id" :options="$client" icon="o-user"
                single searchable />

            <x-choices-offline placeholder="Pilih Barang" wire:model.live="barang_id" :options="$barang" icon="o-flag"
                single searchable />

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

    <!-- ✅ MODAL UBAH STATUS -->
    <x-modal wire:model="statusModal" title="Ubah Status Transaksi" separator>
        <div class="space-y-4">

            <x-input label="Invoice" value="{{ $selectedInv ?: '-' }}" readonly />

            <x-select label="Status Baru" wire:model="newStatus" :options="[['id' => 'Lunas', 'name' => 'Lunas'], ['id' => 'Hutang', 'name' => 'Hutang']]" />
        </div>

        <x-slot:actions>
            <x-button label="Batal" @click="$wire.statusModal=false" />
            <x-button label="Simpan" class="btn-primary" wire:click="updateStatus" spinner />
        </x-slot:actions>
    </x-modal>

</div>
