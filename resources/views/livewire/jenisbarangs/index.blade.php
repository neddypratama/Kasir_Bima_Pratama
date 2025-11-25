<?php

use App\Models\JenisBarang;
use App\Models\Kategori;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Rule;

new class extends Component {
    use Toast;
    use WithPagination;
    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public int $filter = 0;

    public $page = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public int $perPage = 25; // Default jumlah data per halaman

    public bool $editModal = false; // Untuk menampilkan modal

    public ?JenisBarang $editingJenisBarang = null; // Menyimpan data JenisBarang yang sedang diedit

    public string $editingName = '';
    public string $editingDeskripsi = ''; // Menyimpan nilai input untuk nama JenisBarang

    public bool $createModal = false; // Untuk menampilkan modal create

    public string $newName = '';
    public string $newDeskripsi = ''; // Untuk menyimpan input nama JenisBarang baru

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->resetPage();
        $this->success('Filters cleared.', position: 'toast-top');
    }

    // Delete action
    public function delete($id): void
    {
        $jenisbarang = JenisBarang::findOrFail($id);
        $jenisbarang->delete();
        $this->warning("Jenis Barang $jenisbarang->name berhasil dihapus", position: 'toast-top');
    }

    public function create(): void
    {
        $this->newName = ''; // Reset input sebelum membuka modal
        $this->newDeskripsi = '';
        $this->createModal = true;
    }

    public function saveCreate(): void
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newDeskripsi' => 'nullable',
        ]);

        JenisBarang::create(['name' => $this->newName, 'deskripsi' => $this->newDeskripsi]);

        $this->createModal = false;
        $this->success('Jenis Barang created successfully.', position: 'toast-top');
    }

    public function edit($id): void
    {
        $this->editingJenisBarang = JenisBarang::find($id);

        if ($this->editingJenisBarang) {
            $this->editingName = $this->editingJenisBarang->name;
            $this->editingDeskripsi = $this->editingJenisBarang->deskripsi;
            $this->editModal = true; // Tampilkan modal
        }
    }

    public function saveEdit(): void
    {
        if ($this->editingJenisBarang) {
            $this->validate([
                'editingName' => 'required|string|max:255',
                'editingDeskripsi' => 'nullable',
            ]);
            $this->editingJenisBarang->update(['name' => $this->editingName, 'deskripsi' => $this->editingDeskripsi, 'updated_at' => now()]);
            $this->editModal = false;
            $this->success('Jenis Barang updated successfully.', position: 'toast-top');
        }
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'deskripsi', 'label' => 'Deskripsi', 'class' => 'w-100'],
            ['key' => 'barangs_count', 'label' => 'Barang'], // Gunakan `users_count`
            ['key' => 'created_at', 'label' => 'Tanggal dibuat', 'class' => 'w-30'],
        ];
    }

    public function jenisbarangs(): LengthAwarePaginator
    {
        return JenisBarang::query()
            ->withCount('barangs') // Menghitung jumlah users di setiap JenisBarang
            ->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function with(): array
    {
        return [
            'jenisbarangs' => $this->jenisbarangs(),
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
    <x-header title="Daftar Jenis Barang" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Create" @click="$wire.create()" responsive icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4  items-end mb-4">
        <div class="md:col-span-1">
            <x-select label="Show entries" :options="$pages" wire:model.live="perPage" class="w-15" />
        </div>
        <div class="md:col-span-7">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass"
                class="" />
        </div>
        <!-- Dropdown untuk jumlah data per halaman -->
    </div>

    <!-- TABLE wire:poll.5s="users"  -->
    <x-card>
        <x-table :headers="$headers" :rows="$jenisbarangs" :sort-by="$sortBy" with-pagination
            @row-click="$wire.edit($event.detail.id)">
            @scope('cell_barangs_count', $jenisbarang)
                <span>{{ $jenisbarang->barangs_count }}</span>
            @endscope
            @scope('actions', $jenisbarangs)
                <x-button icon="o-trash" wire:click="delete({{ $jenisbarangs['id'] }})"
                    wire:confirm="Yakin ingin menghapus {{ $jenisbarangs['name'] }}?" spinner
                    class="btn-ghost btn-sm text-red-500" />
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="createModal" title="Tambah Jenis Barang">
        <div class="grid gap-4">
            <x-input label="Nama Jenis Barang" wire:model.live="newName" />
            <x-textarea label="Deskripsi Jenis Barang" wire:model.live="newDeskripsi" placeholder="Here ..." />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-mark" @click="$wire.createModal=false" />
            <x-button label="Create" icon="o-check" class="btn-primary" wire:click="saveCreate" spinner/>
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="editModal" title="Edit Jenis Barang">
        <div class="grid gap-4">
             <x-input label="Nama Jenis Barang" wire:model.live="editingName" />
            <x-textarea label="Deskripsi Jenis Barang" wire:model.live="editingDeskripsi" placeholder="Here ..." />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-mark" @click="$wire.editModal=false" />
            <x-button label="Save" icon="o-check" class="btn-primary" wire:click="saveEdit" spinner/>
        </x-slot:actions>
    </x-modal>
</div>
