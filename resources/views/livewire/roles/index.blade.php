<?php

use App\Models\Role;
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

    // Create a public property.
    // public int $country_id = 0;

    public int $filter = 0;

    public $page = [['id' => 25, 'name' => '25'], ['id' => 50, 'name' => '50'], ['id' => 100, 'name' => '100'], ['id' => 500, 'name' => '500']];

    public int $perPage = 25; // Default jumlah data per halaman

    public bool $editModal = false; // Untuk menampilkan modal

    public ?Role $editingRole = null; // Menyimpan data role yang sedang diedit

    public string $editingName = '';
    public string $editingDeskripsi = ''; // Menyimpan nilai input untuk nama role

    public bool $createModal = false; // Untuk menampilkan modal create

    public string $newRoleName = '';
    public string $newRoleDeskripsi = ''; // Untuk menyimpan input nama role baru

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
        $kategori = Role::findOrFail($id);
        $kategori->delete();
        $this->warning("Role $kategori->name akan dihapus", position: 'toast-top');
    }

    public function create(): void
    {
        $this->newRoleName = ''; // Reset input sebelum membuka modal
        $this->newRoleDeskripsi = '';
        $this->createModal = true;
    }

    public function saveCreate(): void
    {
        $this->validate([
            'newRoleName' => 'required|string|max:255',
            'newRoleDeskripsi' => 'nullable',
        ]);

        Role::create(['name' => $this->newRoleName, 'deskripsi' => $this->newRoleDeskripsi]);

        $this->createModal = false;
        $this->success('Role created successfully.', position: 'toast-top');
    }

    public function edit($id): void
    {
        $this->editingRole = Role::find($id);

        if ($this->editingRole) {
            $this->validate([
                'editingName' => 'required|string|max:255',
                'editingDeskripsi' => 'nullable',
            ]);
            $this->editingName = $this->editingRole->name;
            $this->editingDeskripsi = $this->editingRole->deskripsi;
            $this->editModal = true; // Tampilkan modal
        }
    }

    public function saveEdit(): void
    {
        if ($this->editingRole) {
            $this->editingRole->update(['name' => $this->editingName, 'deskripsi' => $this->editingDeskripsi, 'updated_at' => now()]);
            $this->editModal = false;
            $this->success('Role updated successfully.', position: 'toast-top');
        }
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'deskripsi', 'label' => 'Deskripsi', 'class' => 'w-100'],
            ['key' => 'users_count', 'label' => 'User', 'class' => 'w-64'], // Gunakan `users_count`
            ['key' => 'created_at', 'label' => 'Tanggal dibuat', 'class' => 'w-30'],
        ];
    }

    public function roles(): LengthAwarePaginator
    {
        return Role::query()
            ->withCount('users') // Menghitung jumlah users di setiap role
            ->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%$this->search%"))
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
            'roles' => $this->roles(),
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
    <x-header title="Daftar Role" separator progress-indicator>
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
        <x-table :headers="$headers" :rows="$roles" :sort-by="$sortBy" with-pagination
            @row-click="$wire.edit($event.detail.id)">
            @scope('cell_users_count', $role)
                <span>{{ $role->users_count }}</span>
            @endscope
            @scope('actions', $roles)
                <x-button icon="o-trash" wire:click="delete({{ $roles['id'] }})"
                    wire:confirm="Yakin ingin menghapus {{ $roles['name'] }}?" spinner
                    class="btn-ghost btn-sm text-red-500" />
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="createModal" title="Create Role">
        <div class="grid gap-4">
            <x-input label="Role Name" wire:model.live="newRoleName" />
            <x-textarea label="Role Deskripsi" wire:model.live="newRoleDeskripsi" placeholder="Here ..." />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-mark" @click="$wire.createModal=false" />
            <x-button label="Create" icon="o-check" class="btn-primary" wire:click="saveCreate" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="editModal" title="Edit Role">
        <div class="grid gap-4">
            <x-input label="Role Name" wire:model.live="editingName" />
            <x-textarea label="Role Deskripsi" wire:model.live="editingDeskripsi" placeholder="Here ..." />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" icon="o-x-mark" @click="$wire.editModal=false" />
            <x-button label="Save" icon="o-check" class="btn-primary" wire:click="saveEdit" />
        </x-slot:actions>
    </x-modal>
</div>
