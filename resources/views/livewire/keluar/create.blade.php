<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\Kategori;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\Client;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;

new class extends Component {
    use Toast;

    #[Rule('required|unique:transaksis,invoice')]
    public string $invoice = '';

    #[Rule('required')]
    public ?string $name = '';

    #[Rule('required')]
    public ?int $user_id = null;

    public float $total = 0;

    #[Rule('required')]
    public ?string $tanggal = null;

    #[Rule('required')]
    public ?int $kategori_id = 0;

    #[Rule('nullable|array|min:0')]
    public array $details = [];

    public function with(): array
    {
        return [
            'kategori' => Kategori::where('name', 'like', 'Beban%')->get(),
        ];
    }

    /* =====================
        MOUNT
    ====================== */
    public function mount(): void
    {
        $this->user_id = auth()->id();
        $this->tanggal = now()->format('Y-m-d\TH:i');
        $this->updatedTanggal($this->tanggal);
    }

    public function updatedTanggal($value): void
    {
        $tanggal = \Carbon\Carbon::parse($value)->format('Ymd');
        $rand = Str::upper(Str::random(4));

        $this->invoice = "INV-$tanggal-BBN-$rand";
    }

    /* =====================
        SAVE
    ====================== */
    public function save(): void
    {
        $this->validate([
            'details' => 'nullable|array|min:0',
        ]);

        $kasir = Transaksi::create([
            'invoice' => $this->invoice,
            'name' => $this->name,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => null,
            'type' => 'Debit',
            'total' => $this->total,
            'status' => 'Lunas',
            'uang' => null,
            'kembalian' => null,
        ]);

        DetailTransaksi::create([
            'transaksi_id' => $kasir->id,
            'barang_id' => null,
            'kategori_id' => $this->kategori_id,
            'value' => $this->total,
            'kuantitas' => null,
            'sub_total' => $this->total,
        ]);

        $this->success('Transaksi berhasil dibuat!', redirectTo: '/keluar');
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Tambah Transaksi Penjualan" separator progress-indicator />

    <x-form wire:submit="save">

        <!-- BASIC INFO -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-3">
                    <x-header title="Basic Info" subtitle="Informasi transaksi" size="text-2xl" />
                </div>
                <div class="col-span-5 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input label="Invoice" wire:model="invoice" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" type="datetime-local" readonly />
                        <x-choices-offline label="Kategori" wire:model="kategori_id" :options="$kategori" option-value="id"
                            option-label="name" placeholder="Pilih Kategori" single searchable />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <x-input label="Deskripsi Pengeluaran" wire:model="name" />
                        </div>
                        <x-input label="Total Pengeluaran" wire:model.live="total" prefix="Rp " money="IDR" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Cancel" link="/keluar" />
            <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>

    </x-form>
</div>
