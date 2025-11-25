<?php

use App\Models\Client;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\ClientExport;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    use Toast;
    use WithPagination;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public int $filter = 0;

    public $page = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public int $perPage = 25; // Default jumlah data per halaman

    public ?string $keterangan_id = null;

    public bool $editModal = false; // Untuk menampilkan modal

    public ?Client $editingClient = null; // Menyimpan data Client yang sedang diedit

    public string $editingName = '';
    public string $editingAlamat = ''; // Menyimpan nilai input untuk nama Client
    public ?string $editingKeterangan = null;

    public bool $createModal = false; // Untuk menampilkan modal create

    public string $newClientName = '';
    public string $newClientAlamat = ''; // Untuk menyimpan input nama Client baru
    public ?string $newClientKeterangan = null;

    public $keterangan = [['id' => 'Pembeli', 'name' => 'Pembeli'], ['id' => 'Supplier', 'name' => 'Supplier']];

    public function create(): void
    {
        $this->newClientName = ''; // Reset input sebelum membuka modal
        $this->newClientAlamat = '';
        $this->newClientKeterangan = null;
        $this->createModal = true;
    }

    public function saveCreate(): void
    {
        $this->validate([
            'newClientName' => 'required|string|max:255|unique:clients,name',
            'newClientAlamat' => 'nullable',
            'newClientKeterangan' => 'in:Pembeli,Supplier',
        ]);

        Client::create(['name' => $this->newClientName, 'alamat' => $this->newClientAlamat, 'keterangan' => $this->newClientKeterangan, 'created_at' => now(), 'updated_at' => now()]);

        $this->createModal = false;
        $this->success('Client created successfully.', position: 'toast-top');
    }

    public function edit($id): void
    {
        $this->editingClient = Client::find($id);

        if ($this->editingClient) {
            $this->editingName = $this->editingClient->name;
            $this->editingAlamat = $this->editingClient->alamat;
            $this->editingKeterangan = $this->editingClient->keterangan;
            $this->editModal = true; // Tampilkan modal
        }
    }

    public function saveEdit(): void
    {
        if ($this->editingClient) {
            $this->validate([
                'editingName' => 'required|string|max:255',
                'editingAlamat' => 'nullable',
                'editingKeterangan' => 'in:Pembeli,Supplier',
            ]);
            $this->editingClient->update(['name' => $this->editingName, 'alamat' => $this->editingAlamat, 'keterangan' => $this->editingKeterangan,'updated_at' => now()]);
            $this->editModal = false;
            $this->success('Client updated successfully.', position: 'toast-top');
        }
    }

    // Clear filters
    public function clear(): void
    {
        $this->reset('search', 'keterangan_id', 'filter',);
        $this->resetPage();
        $this->success('Filters cleared.', position: 'toast-top');
    }

    public function export(): mixed
    {
        $this->success('Export dimulai...', position: 'toast-top');

        return Excel::download(new ClientExport(), 'client.xlsx');
    }

    // Delete action
    public function delete($id): void
    {
        $client = Client::findOrFail($id);
        $client->delete();
        $this->warning("Client $client->name akan dihapus", position: 'toast-top');
    }

    // Table headers
    public function headers(): array
    {
        return [['key' => 'id', 'label' => '#', 'class' => 'w-1'], ['key' => 'name', 'label' => 'Name', 'class' => 'w-32'], ['key' => 'alamat', 'label' => 'Alamat', 'sortable' => false, 'class' => 'w-48'], ['key' => 'keterangan', 'label' => 'Keterangan', 'sortable' => false, 'class' => 'w-48']];
    }

    public function clients(): LengthAwarePaginator
    {
        return Client::query()
            ->with('transaksi.details.kategori') // agar eager load, lebih hemat query
            ->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%$this->search%"))
            ->when($this->keterangan_id, fn(Builder $q) => $q->where('keterangan', $this->keterangan_id))
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function with(): array
    {
        if ($this->filter >= 0 && $this->filter < 2) {
            if (!$this->search == null) {
                $this->filter = 1;
            } else {
                $this->filter = 0;
            }
        }
        return [
            'clients' => $this->clients(),
            'keterangan' => $this->keterangan,
            'headers' => $this->headers(),
            'perPage' => $this->perPage,
            'pages' => $this->page,
        ];
    }

    // Reset pagination when any component property changes
    public function updated($property): void
    {
        if (!is_array($property) && $property != '') {
            $this->resetPage();
        }
    }
};

?>

<div>
    <!-- HEADER -->
    <x-header title="Daftar Klien" separator progress-indicator>
        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2">
                <x-button wire:click="export" icon="fas.download" primary>Export Excel</x-button>
                <x-button label="Create" @click="$wire.create()" responsive icon="o-plus" class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-header>

    <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4  items-end mb-4">
        <div class="md:col-span-1">
            <x-select label="Show entries" :options="$pages" wire:model.live="perPage" />
        </div>
        <div class="md:col-span-6">
            <x-input placeholder="Name..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"
                class="" />
        </div>
        <div class="md:col-span-1">
            <x-button label="Filters" @click="$wire.drawer = true" responsive icon="o-funnel"
                badge="{{ $this->filter }}" badge-classes="badge-primary" />
        </div>
        <!-- Dropdown untuk jumlah data per halaman -->
    </div>

    <!-- TABLE wire:poll.5s="Clients"  -->
    <x-card>
        <x-table :headers="$headers" :rows="$clients" :sort-by="$sortBy" with-pagination
            @row-click="$wire.edit($event.detail.id)">

            {{-- Tombol Aksi --}}
            @scope('actions', $client)
                <x-button icon="o-trash" wire:click="delete({{ $client['id'] }})"
                    wire:confirm="Yakin ingin menghapus {{ $client['name'] }}?" spinner
                    class="btn-ghost btn-sm text-red-500" />
            @endscope
        </x-table>

    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Filters" right separator with-close-button class="lg:w-1/3">
        <div class="grid gap-5">
            <x-input placeholder="Name..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
            <x-select :options="$keterangan" wire:model.live="keterangan_id" placeholder="Pilih keterangan" />
        </div>

        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer=false" />
        </x-slot:actions>
    </x-drawer>

    <x-modal wire:model="createModal" title="Create Client">
        <div class="grid gap-4">
            <x-input label="Client Name" wire:model="newClientName" />
            <x-textarea label="Client Alamat" wire:model="newClientAlamat" placeholder="Here ..." />
            <x-select label="Client Keterangan" wire:model="newClientKeterangan" :options="$keterangan" placeholder="Pilih keterangan" />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-mark" @click="$wire.createModal=false" />
            <x-button label="Create" icon="o-check" class="btn-primary" wire:click="saveCreate" spinner/>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="editModal" title="Edit Client">
        <div class="grid gap-4">
            <x-input label="Client Name" wire:model="editingName" />
            <x-textarea label="Client Alamat" wire:model="editingAlamat" placeholder="Here ..." />
            <x-select label="Client Keterangan" wire:model="editingKeterangan" :options="$keterangan" placeholder="Pilih keterangan" />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-mark" @click="$wire.editModal=false" />
            <x-button label="Save" icon="o-check" class="btn-primary" wire:click="saveEdit" spinner/>
        </x-slot:actions>
    </x-modal>
</div>
