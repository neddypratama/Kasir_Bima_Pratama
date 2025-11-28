<?php

use App\Models\Transaksi;
use App\Models\User;
use App\Models\Client;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\PenjualanSentratExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

new class extends Component {
    use Toast, WithPagination;

    public $today;

    public function mount(): void
    {
        $this->today = Carbon::today();
    }

    public string $search = '';
    public bool $drawer = false;
    public array $sortBy = ['column' => 'id', 'direction' => 'desc'];
    public int $filter = 0;
    public int $client_id = 0;
    public ?string $user_id = null;

    // ✅ FIX: inisialisasi default status sebagai string kosong
    public string $status_id = '';

    // ✅ Option status juga string
    public array $statuses = [['id' => 'Hutang', 'name' => 'Hutang'], ['id' => 'Lunas', 'name' => 'Lunas']];

    public $page = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public int $perPage = 25;
    public bool $exportModal = false;
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function clear(): void
    {
        $this->reset(['search', 'client_id', 'user_id', 'status_id', 'filter', 'startDate', 'endDate']);
        $this->resetPage();
        $this->success('Semua filter berhasil direset!', position: 'toast-top');
    }

    public function openExportModal(): void
    {
        $this->exportModal = true;
    }

    public function export(): mixed
    {
        if (!$this->startDate || !$this->endDate) {
            $this->error('Pilih tanggal mulai & selesai dulu!', position: 'toast-top');
            return null;
        }
        $this->exportModal = false;
        $this->success('Export sedang diproses...', position: 'toast-top');

        return Excel::download(new PenjualanSentratExport($this->startDate, $this->endDate), 'penjualan-pakan.xlsx');
    }

    public function headers(): array
    {
        return [['key' => 'invoice', 'label' => 'Invoice', 'class' => 'w-36'], ['key' => 'tanggal', 'label' => 'Tanggal', 'class' => 'w-16'], ['key' => 'client.name', 'label' => 'Client', 'class' => 'w-16'],['key' => 'user.name', 'label' => 'User', 'class' => 'w-16'], ['key' => 'total', 'label' => 'Total', 'class' => 'w-24', 'format' => ['currency', 0, 'Rp']], ['key' => 'status', 'label' => 'Status', 'class' => 'w-16']];
    }

    public function transaksis(): LengthAwarePaginator
    {
        return Transaksi::query()
            ->with(['client:id,name,keterangan', 'user:id,name'])
            ->when($this->search, function (Builder $q) {
                $q->where('invoice', 'like', "%{$this->search}%");
            })
            ->when($this->status_id !== '', fn(Builder $q) => $q->where('status', $this->status_id)) // ✅ filter string
            ->when($this->user_id, fn(Builder $q) => $q->where('user_id', $this->user_id))
            ->when($this->client_id, fn(Builder $q) => $q->where('client_id', $this->client_id))
            ->when($this->startDate, fn(Builder $q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn(Builder $q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function with(): array
    {
        // ✅ hitung badge filter termasuk status
        $f = 0;
        if ($this->search) {
            $f++;
        }
        if ($this->client_id) {
            $f++;
        }
        if ($this->user_id) {
            $f++;
        }
        if ($this->startDate) {
            $f++;
        }
        if ($this->status_id) {
            $f++;
        }
        $this->filter = $f;

        return [
            'transaksi' => $this->transaksis(),
            'clients' => Client::all(),
            'users' => User::all(),
            'statuses' => $this->statuses,
            'headers' => $this->headers(),
            'pages' => $this->page,
        ];
    }

    public function updated($property): void
    {
        if (!is_array($property) && $this->search !== '') {
            $this->resetPage();
        }
    }
};
?>
<div class="p-4 space-y-6">

    <x-header title="Daftar Transaksi Pakan" progress-indicator separator>
        <x-slot:actions>
            <x-button wire:click="openExportModal" icon="fas.download" primary>
                Export Excel
            </x-button>
        </x-slot:actions>
    </x-header>

    {{-- FILTER BAR --}}
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 items-end mb-4">
        <div class="md:col-span-1">
            <x-select label="Show" :options="$pages" wire:model.live="perPage" />
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

    {{-- TABLE --}}
    <x-card class="overflow-x-auto">
        <x-table :headers="$headers" :rows="$transaksi" :sort-by="$sortBy" with-pagination
            link="transaksis/{id}/show?invoice={invoice}">
            @scope('cell_status', $transaksi)
                @if ($transaksi->status == 'Lunas')
                    <span class="badge badge-success">{{ $transaksi->status }}</span>
                @elseif ($transaksi->status == 'Hutang')
                    <span class="badge badge-error">{{ $transaksi->status }}</span>
                @else
                    <span class="badge badge-ghost">{{ $transaksi->status }}</span>
                @endif
            @endscope

        </x-table>
    </x-card>

    {{-- FILTER DRAWER --}}
    <x-drawer wire:model="drawer" right separator title="Filter Data" with-close-button class="lg:w-1/3">
        <div class="grid gap-2">
            <x-select label="Client" :options="$clients" wire:model.live="client_id" placeholder="Semua"
                placeholder-value="" />
            <x-select label="User" :options="$users" wire:model.live="user_id" placeholder="Semua"
                placeholder-value="" />
            <x-select label="Status" :options="$statuses" wire:model.live="status_id" placeholder="Semua"
                placeholder-value="" />
            <x-input label="Dari" type="date" wire:model.live="startDate" />
            <x-input label="Sampai" type="date" wire:model.live="endDate" />
        </div>

        <x-slot:actions>
            <x-button wire:click="clear" icon="o-x-mark">Reset Filter</x-button>
            <x-button @click="$wire.drawer=false" class="btn-primary" icon="o-check">Selesai</x-button>
        </x-slot:actions>
    </x-drawer>

    {{-- EXPORT MODAL --}}
    <x-modal wire:model="exportModal" separator title="Export Penjualan">
        <div class="grid gap-4">
            <x-input label="Dari Tanggal" type="date" wire:model="startDate" />
            <x-input label="Sampai Tanggal" type="date" wire:model="endDate" />
        </div>

        <x-slot:actions>
            <x-button @click="$wire.exportModal=false">Batal</x-button>
            <x-button wire:click="export" class="btn-primary">Export Data</x-button>
        </x-slot:actions>
    </x-modal>

</div>
