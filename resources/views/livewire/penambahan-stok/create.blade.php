<?php

use Livewire\Volt\Component;
use App\Models\Barang;
use App\Models\StokBatch;
use App\Models\Stok;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\User;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required')]
    public ?int $barang_id = null;

    public ?string $tanggal = null;
    public ?int $user_id = null;
    public float $stok = 0;
    public float $awal = 0;

    #[Rule('nullable|numeric|min:0')]
    public float $qty = 0;

    #[Rule('nullable|numeric|min:0')]
    public float $harga = 0;

    public function with(): array
    {
        return [
            'users' => User::all(),
            'barangs' => Barang::all(),
        ];
    }

    public function mount(): void
    {
        $this->user_id = auth()->id();
        $this->tanggal = now()->format('Y-m-d\TH:i:s');
    }

    public function updatedBarangId($id): void
    {
        if ($id) {
            $barang = StokBatch::where('barang_id', $id)->get();
            $this->stok = $barang->sum('qty_sisa') ?? 0;
            $this->awal = $barang->sum('qty_sisa') ?? 0;
        }
    }

    public function updated($field): void
    {
        if (in_array($field, ['qty'])) {
            $barang = StokBatch::where('barang_id', $this->barang_id)->get();
            if ($barang) {
                $stok_awal = $barang->sum('qty_sisa') ?? 0;
                $stok_baru = $stok_awal + $this->qty;
                $this->stok = max(0, $stok_baru);
            }
        }
    }

    public function save(): void
    {
        $this->validate();

        $barang = Barang::find($this->barang_id);
        if (!$barang) {
            $this->error('Barang tidak ditemukan.');
            return;
        }

        $barang->update(['stok' => $this->stok]);

        StokBatch::create([
            'user_id' => $this->user_id,
            'barang_id' => $this->barang_id,
            'tanggal' => $this->tanggal,
            'qty_masuk' => $this->qty,
            'qty_sisa' => $this->qty,
            'harga' => $this->harga,
        ]);

        $this->success('Stok berhasil diperbarui!', redirectTo: '/penambahan-stok');
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Tambah Transaksi Stok" separator progress-indicator />

    <x-form wire:submit="save">
        <!-- SECTION: Basic Info -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Basic Info" subtitle="Buat transaksi baru" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input label="User" :value="auth()->user()->name" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" icon="o-calendar" type="datetime-local"/>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="col-span-2">
                            <x-choices-offline placeholder="Pilih Barang" wire:model.live="barang_id" :options="$barangs"
                                single searchable clearable label="Barang" />
                        </div>
                        <x-input label="Stok Awal" wire:model.live="awal" type="number" step="0.01" readonly />
                        <x-input label="Stok Sekarang" wire:model.live="stok" type="number" step="0.01" readonly />
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
                <x-button spinner label="Cancel" link="/stok" />
                <x-button spinner label="Create" icon="o-paper-airplane" spinner="save" type="submit"
                    class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-form>
</div>
