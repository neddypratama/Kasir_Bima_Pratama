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

    #[Rule('required|string')]
    public string $name = '';

    #[Rule('required|exists:jenis_barangs,id')]
    public ?int $jenis_id = null;

    #[Rule('required|numeric|min:0')]
    public float $stok = 0.0;

    #[Rule('required|string')]
    public string $satuan = '';

    #[Rule('nullable|numeric|min:0')]
    public float $hpp = 0.0;

    #[Rule('nullable|numeric|min:0')]
    public float $harga = 0.0;

    public function with(): array
    {
        return [
            'jenisbarang' => JenisBarang::all(),
        ];
    }

    public function mount(Barang $barang): void
    {
        $this->barang = $barang;

        $this->name = $barang->name;
        $this->jenis_id = $barang->jenis_id;
        $this->stok = $barang->stok;
        $this->satuan = $barang->satuan;
        $this->hpp = $barang->hpp ?? 0;
        $this->harga = $barang->harga ?? 0;
    }

    public function save(): void
    {
        $this->validate(); 

        $barang = Barang::find($this->barang->id);

        $barang->update([
            'name' => $this->name,
            'jenis_id' => $this->jenis_id,
            'stok' => $this->stok,
            'satuan' => $this->satuan,
            'hpp' => $this->hpp,
            'harga' => $this->harga,
        ]);

        $this->success('Barang berhasil dibuat!', redirectTo: '/barangs');
    }
};

?>

<div>
    <x-header title="Edit {{$barang->name}}" separator />

    <x-form wire:submit="save">

        {{-- ===================== BASIC SECTION ===================== --}}
        <div class="lg:grid grid-cols-8 gap-5">
            <div class="col-span-3">
                <x-header title="Basic" subtitle="Informasi dasar barang" size="text-2xl" />
            </div>

            <div class="col-span-5 grid gap-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <x-input label="Nama Barang" wire:model="name" placeholder="Contoh: Dog Food Premium" />
                    <x-select label="Jenis Barang" wire:model="jenis_id" :options="$jenisbarang" option-label="name"
                        option-value="id" placeholder="Pilih jenis barang" />
                    <x-input label="Harga Pokok (HPP)" wire:model="hpp" prefix="Rp " money="IDR" />

                </div>
            </div>
        </div>

        <hr class="my-5" />

        {{-- ===================== DETAILS SECTION ===================== --}}
        <div class="lg:grid grid-cols-8 gap-5">
            <div class="col-span-3">
                <x-header title="Details" subtitle="Informasi lengkap barang" size="text-2xl" />
            </div>
            <div class="col-span-5 grid gap-3">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <x-input label="Harga Jual" wire:model="harga" prefix="Rp " money="IDR" />
                    </div>
                    <x-input label="Stok" wire:model="stok" type="number" min="0" step="0.01" />
                    <x-input label="Satuan Dasar" wire:model.live="satuan" placeholder="Contoh: Kg" />
                </div>
            </div>
        </div>

        {{-- ===================== ACTION BUTTON ===================== --}}
        <x-slot:actions>
            <x-button label="Kembali" link="/barangs" />
            <x-button label="Save" icon="o-paper-airplane" spinner="save" type="submit" class="btn-primary" />
        </x-slot:actions>

    </x-form>
</div>
