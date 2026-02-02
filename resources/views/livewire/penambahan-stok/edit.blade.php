<?php

use Livewire\Volt\Component;
use App\Models\Barang;
use App\Models\User;
use App\Models\StokBatch;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast;

    public StokBatch $batch;

    #[Rule('required')]
    public int $barang_id;

    #[Rule('required')]
    public int $user_id;

    #[Rule('required')]
    public ?string $tanggal = null;

    #[Rule('required|numeric|min:0.01')]
    public float $qty;

    #[Rule('required|numeric|min:0')]
    public float $harga;

    public float $stok_awal = 0;
    public float $stok_sekarang = 0;

    public function with(): array
    {
        return [
            'users' => User::all(),
            'barangs' => Barang::all(),
        ];
    }

    public function mount(StokBatch $batch): void
    {
        $this->batch = $batch;
        $this->tanggal = $batch->tanggal;
        $this->barang_id = $batch->barang_id;
        $this->user_id = $batch->user_id;
        $this->qty = $batch->qty_masuk;
        $this->harga = $batch->harga;

        $this->stok_sekarang = StokBatch::where('barang_id', $batch->barang_id)->sum('qty_sisa');
        $this->stok_awal = $this->stok_sekarang - $batch->qty_masuk;
    }

    public function updatedQty(): void
    {
        $this->stok_sekarang = max(0, $this->stok_awal + $this->qty);
    }

    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            /** 🔹 1. ROLLBACK STOK LAMA */
            $rollbackQty = $this->batch->qty_sisa;

            StokBatch::where('id', $this->batch->id)->delete();

            /** 🔹 2. SIMPAN BATCH BARU */
            StokBatch::create([
                'user_id' => $this->user_id,
                'barang_id' => $this->barang_id,
                'tanggal' => $this->tanggal,
                'qty_masuk' => $this->qty,
                'qty_sisa' => $this->qty,
                'harga' => $this->harga,
            ]);
        });

        $this->success('Stok berhasil diperbarui', redirectTo: '/penambahan-stok');
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Edit Transaksi Stok" separator progress-indicator />

    <x-form wire:submit="save">
        <!-- SECTION: Basic Info -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Basic Info" subtitle="Buat transaksi baru" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input label="User" :value="$users->find($user_id)->name ?? 'User tidak ditemukan'" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" icon="o-calendar" type="datetime-local" />
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="col-span-2">
                            <x-choices-offline placeholder="Pilih Barang" wire:model.live="barang_id" :options="$barangs"
                                single searchable clearable label="Barang" />
                        </div>
                        <x-input label="Stok Awal" wire:model.live="stok_awal" type="number" step="0.01" readonly />
                        <x-input label="Stok Sekarang" wire:model.live="stok_sekarang" type="number" step="0.01"
                            readonly />
                    </div>
                </div>
            </div>
        </x-card>

        <!-- SECTION: Detail Items -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Detail Items" subtitle="Tambah detail transaksi" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end rounded-xl">
                        <div class="col-span-2">
                            <x-input label="Harga" wire:model.live="harga" prefix="Rp " money="IDR" />
                        </div>
                        <x-input label="Stok Masuk" wire:model.lazy="qty" type="number" step="0.01"
                            min="0" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2 justify-end">
                <x-button spinner label="Cancel" link="/penambahan-stok" />
                <x-button spinner label="Create" icon="o-paper-airplane" spinner="save" type="submit"
                    class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-form>
</div>
