<?php

use App\Models\Transaksi;
use App\Models\User;
use App\Models\Client;
use App\Models\Kategori;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\TransaksiExport;
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

    public string $status_id = '';

    // 🔥 FILTER BARU
    public ?string $kategori_name = null;

    public array $statuses = [['id' => 'Hutang', 'name' => 'Hutang'], ['id' => 'Lunas', 'name' => 'Lunas']];

    public $page = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public int $perPage = 25;
    public bool $exportModal = false;
    public ?string $startDate = null;
    public ?string $endDate = null;

    /* ===================== */
    public function clear(): void
    {
        $this->reset([
            'search',
            'client_id',
            'user_id',
            'status_id',
            'filter',
            'startDate',
            'endDate',
            'kategori_name', // 🔥 reset kategori
        ]);

        $this->resetPage();

        $this->success('Semua filter berhasil direset!', position: 'toast-top');
    }

    /* ===================== */
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

        return Excel::download(new TransaksiExport($this->startDate, $this->endDate), 'transaksi.xlsx');
    }

    /* ===================== */
    public function headers(): array
    {
        return [['key' => 'invoice', 'label' => 'Invoice'], ['key' => 'tanggal', 'label' => 'Tanggal'], ['key' => 'client.name', 'label' => 'Client'], ['key' => 'user.name', 'label' => 'User'], ['key' => 'total', 'label' => 'Total', 'format' => ['currency', 0, 'Rp']], ['key' => 'status', 'label' => 'Status']];
    }

    /* ===================== */
    public function transaksis(): LengthAwarePaginator
    {
        return Transaksi::query()
            ->with(['client:id,name', 'user:id,name'])

            ->when($this->search, function (Builder $q) {
                $q->where('invoice', 'like', "%{$this->search}%");
            })

            ->when($this->status_id !== '', fn($q) => $q->where('status', $this->status_id))

            ->when($this->user_id, fn($q) => $q->where('user_id', $this->user_id))

            ->when($this->client_id, fn($q) => $q->where('client_id', $this->client_id))

            // 🔥 FILTER KATEGORI (DETAIL TRANSAKSI)
            ->when($this->kategori_name, function ($q) {
                $q->whereHas('details.kategori', function ($q2) {
                    $q2->where('id', $this->kategori_name);
                });
            })

            ->when($this->startDate, fn($q) => $q->whereDate('tanggal', '>=', $this->startDate))

            ->when($this->endDate, fn($q) => $q->whereDate('tanggal', '<=', $this->endDate))

            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    /* ===================== */
    public function with(): array
    {
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
        if ($this->status_id) {
            $f++;
        }
        if ($this->startDate) {
            $f++;
        }
        if ($this->kategori_name) {
            $f++;
        } // 🔥 badge kategori

        $this->filter = $f;

        return [
            'transaksi' => $this->transaksis(),
            'clients' => Client::all(),
            'users' => User::all(),
            'statuses' => $this->statuses,
            'headers' => $this->headers(),
            'pages' => $this->page,
            'kategoris' => Kategori::all(), // 🔥 dropdown
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

    <x-header title="Daftar Transaksi Pakan" separator />
    <!-- FILTER BAR -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-4">

        <x-select label="Show" :options="$pages" wire:model.live="perPage" />

        <div class="md:col-span-6">
            <x-input wire:model.live.debounce="search" placeholder="Cari Invoice..." clearable />
        </div>

        <x-button label="Filters" @click="$wire.drawer = true" badge="{{ $this->filter }}" />
    </div>

    <!-- TABLE -->
    <x-card>
        <x-table :headers="$headers" :rows="$transaksi" with-pagination>
            @scope('cell_status', $row)
                <span class="badge 
                    {{ $row->status == 'Lunas' ? 'badge-success' : 'badge-error' }}">
                    {{ $row->status }}
                </span>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" right title="Filter">

        <x-select label="Client" :options="$clients" wire:model.live="client_id" placeholder="Semua" />
        <x-select label="User" :options="$users" wire:model.live="user_id" placeholder="Semua" />
        <x-select label="Status" :options="$statuses" wire:model.live="status_id" placeholder="Semua" />

        <!-- 🔥 FILTER KATEGORI -->
        <x-select label="Kategori" :options="$kategoris" wire:model.live="kategori_name" placeholder="Semua" />

        <x-input type="date" wire:model.live="startDate" label="Dari" />
        <x-input type="date" wire:model.live="endDate" label="Sampai" />

        <x-slot:actions>
            <x-button wire:click="clear">Reset</x-button>
            <x-button class="btn-primary" @click="$wire.drawer=false">Apply</x-button>
        </x-slot:actions>

    </x-drawer>

    <!-- EXPORT -->
    <x-modal wire:model="exportModal" title="Export">

        <x-input type="date" wire:model="startDate" label="Dari" />
        <x-input type="date" wire:model="endDate" label="Sampai" />

        <x-slot:actions>
            <x-button @click="$wire.exportModal=false">Batal</x-button>
            <x-button wire:click="export" class="btn-primary">Export</x-button>
        </x-slot:actions>

    </x-modal>

</div>
