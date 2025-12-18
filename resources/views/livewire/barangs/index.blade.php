<?php

use App\Models\Barang;
use App\Models\JenisBarang;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\BarangExport;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    use Toast;
    use WithPagination;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    // Create a public property.
    public int $jenis_id = 0;

    public int $filter = 0;

    public $page = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public int $perPage = 25; // Default jumlah data per halaman

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->resetPage();
        $this->success('Filters cleared.', position: 'toast-top');
    }

    public function export(): mixed
    {
        $this->success('Export dimulai...', position: 'toast-top');

        return Excel::download(new BarangExport(), 'barang.xlsx');
    }

    // Delete action
    public function delete($id): void
    {
        $barang = Barang::findOrFail($id);
        $barang->delete();
        $this->warning("Barang $barang->name akan dihapus", position: 'toast-top');
    }

    // Table headers
    public function headers(): array
    {
        return [['key' => 'id', 'label' => '#', 'class' => 'w-1'], ['key' => 'jenis_name', 'label' => 'Jenis Barang'], ['key' => 'name', 'label' => 'Name'], ['key' => 'stok', 'label' => 'Stok'], ['key' => 'hpp', 'label' => 'Harga Pokok', 'class' => 'w-24', 'format' => ['currency', 0, 'Rp']], ['key' => 'harga_eceran', 'label' => 'Harga Eceran', 'class' => 'w-24', 'format' => ['currency', 0, 'Rp']], ['key' => 'harga_sak', 'label' => 'Harga Partai', 'class' => 'w-24', 'format' => ['currency', 0, 'Rp']]];
    }

    public function barangs(): LengthAwarePaginator
    {
        return Barang::query()->withAggregate('jenis', 'name')->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%$this->search%"))->when($this->jenis_id, fn(Builder $q) => $q->where('jenis_id', $this->jenis_id))->orderBy(...array_values($this->sortBy))->paginate($this->perPage);
    }

    public function with(): array
    {
        $filter = 0;

        if ($this->search !== '') {
            $filter++;
        }

        if ($this->jenis_id !== 0) {
            $filter++;
        }

        $this->filter = $filter;

        return [
            'barangs' => $this->barangs(),
            'headers' => $this->headers(),
            'jenisbarangs' => JenisBarang::all(),
            'perPage' => $this->perPage,
            'pages' => $this->page,
        ];
    }

    // Reset pagination when any component property changes
    public function updated($property): void
    {
        if (in_array($property, ['search', 'jenis_id', 'perPage'])) {
            $this->resetPage();
        }
    }
};

?>

<div>
    <!-- HEADER -->
    <x-header title="Daftar Barang" separator progress-indicator>
        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2">
                <x-button wire:click="export" icon="fas.download" primary>Export Excel</x-button>
                <x-button label="Create" link="/barangs/create" responsive icon="o-plus" class="btn-primary" />
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

    <!-- TABLE wire:poll.5s="Barangs"  -->
    <x-card>
        <x-table :headers="$headers" :rows="$barangs" :sort-by="$sortBy" with-pagination
            link="barangs/{id}/edit?name={name}&jenis={jenis.name}">
            @scope('cell_stok', $barang)
                {{ $barang->stok }} {{ $barang->satuan }}
            @endscope

            @scope('actions', $barang)
                <div class="flex">
                    <x-button icon="o-trash" wire:click="delete({{ $barang['id'] }})"
                        wire:confirm="Yakin ingin menghapus {{ $barang['name'] }}?" spinner
                        class="btn-ghost btn-sm text-red-500" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Filters" right separator with-close-button class="lg:w-1/3">
        <div class="grid gap-5">
            <x-input placeholder="Name..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
            <x-select placeholder="Jenis Barang" wire:model.live="jenis_id" :options="$jenisbarangs" icon="o-flag"
                placeholder-value="0" />
        </div>

        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer=false" />
        </x-slot:actions>
    </x-drawer>
</div>
