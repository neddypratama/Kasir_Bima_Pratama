<?php

use Livewire\Volt\Component;
use App\Models\Barang;
use App\Models\JenisBarang;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;

new class extends Component {
    use Toast, WithFileUploads;

    public Barang $barang;

    #[Rule('required|string|unique:barangs,name')]
    public string $name = '';

    #[Rule('required|exists:jenis_barangs,id')]
    public ?int $jenis_id = null;

    #[Rule('required|numeric|min:0')]
    public float $stok = 0.0;

    #[Rule('nullable|numeric|min:0')]
    public float $hpp = 0.0;

    #[Rule('required|numeric|min:0')]
    public float $harga = 0.0;

    public function with(): array
    {
        return [
            'jenisbarang' => JenisBarang::all(),
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        Barang::create($data);

        $this->success('Barang berhasil dibuat!', redirectTo: '/barangs');
    }
};

?>

<div>
    <x-header title="Tambah Barang" separator />

    <x-form wire:submit="save">

        {{-- ===================== BASIC SECTION ===================== --}}
        <div class="lg:grid grid-cols-7 gap-5">
            <div class="col-span-2">
                <x-header title="Basic" subtitle="Informasi dasar barang" size="text-2xl" />
            </div>

            <div class="col-span-5 grid gap-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-input label="Nama Barang" wire:model="name" placeholder="Contoh: Dog Food Premium" />
                    <x-select label="Jenis Barang" wire:model="jenis_id" :options="$jenisbarang" option-label="name"
                        option-value="id" placeholder="Pilih jenis barang" />
                </div>
            </div>
        </div>

        <hr class="my-5" />

        {{-- ===================== DETAILS SECTION ===================== --}}
        <div class="lg:grid grid-cols-7 gap-5">
            <div class="col-span-2">
                <x-header title="Details" subtitle="Informasi lengkap barang" size="text-2xl" />
            </div>

            <div class="col-span-5 grid gap-3">

                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">

                    {{-- Kolom 1: Stok --}}
                    <div class="sm:col-span-1">
                        <x-input label="Stok" wire:model="stok" type="number" min="0" step="0.01" />
                    </div>

                    {{-- Kolom 2–5: Harga --}}
                    <div class="sm:col-span-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-input label="Harga Pokok (HPP)" wire:model="hpp" prefix="Rp " money="IDR" />

                        <x-input label="Harga Jual" wire:model="harga" prefix="Rp " money="IDR" />
                    </div>

                </div>

            </div>
        </div>

        {{-- ===================== ACTION BUTTON ===================== --}}
        <x-slot:actions>
            <x-button label="Cancel" link="/barangs" />
            <x-button label="Save" icon="o-paper-airplane" spinner="save" type="submit" class="btn-primary" />
        </x-slot:actions>

    </x-form>
</div>
