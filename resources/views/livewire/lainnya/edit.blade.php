<?php

use Livewire\Volt\Component;
use App\Models\{Barang, Client, Transaksi, DetailTransaksi, Kategori};
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;

new class extends Component {
    use Toast;

    public Transaksi $transaksi;

    #[Rule('required')]
    public ?string $invoice = null;

    #[Rule('required')]
    public ?string $name = null;

    #[Rule('required')]
    public ?string $tanggal = null;

    #[Rule('required')]
    public ?int $kategori_id = null;

    #[Rule('required')]
    public float $total = 0;

    #[Rule('required')]
    public ?string $bayar = null;

    /* =====================
        WITH
    ====================== */
    public function with(): array
    {
        return [
            'kategori' => Kategori::where('name', 'like', 'Pendapatan Lainnya%')->get(),
            'bayars' => [['id' => 'Cash', 'name' => 'Cash'], ['id' => 'Transfer', 'name' => 'Transfer']],
        ];
    }

    /* =====================
        MOUNT
    ====================== */
    public function mount(Transaksi $transaksi): void
    {
        $this->transaksi = $transaksi->load('details');
        $this->invoice = $transaksi->invoice;
        $this->name = $transaksi->name;
        $this->tanggal = $transaksi->tanggal;
        $this->total = $transaksi->total;
        $this->bayar = $transaksi->bayar;
        $this->kategori_id = $transaksi->details->first()->kategori_id;
    }

    /* =====================
        SAVE UPDATE
    ====================== */
    public function save(): void
    {
        $this->validate();

        // hapus detail lama
        DetailTransaksi::where('transaksi_id', $this->transaksi->id)->delete();

        $this->transaksi->update([
            'name' => $this->name,
            'total' => $this->total,
            'bayar' => $this->bayar,
        ]);

        DetailTransaksi::create([
            'transaksi_id' => $this->transaksi->id,
            'barang_id' => null,
            'kategori_id' => $this->kategori_id,
            'value' => $this->total,
            'kuantitas' => null,
            'sub_total' => $this->total,
        ]);

        $this->success('Transaksi berhasil diperbarui', redirectTo: '/lainnya');
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Update {{ $transaksi->invoice }}" separator progress-indicator />

    <x-form wire:submit="save">

        <!-- BASIC INFO -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Basic Info" subtitle="Informasi transaksi" size="text-2xl" />
                </div>
                <div class="col-span-6 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input label="Invoice" wire:model="invoice" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" type="datetime-local" readonly />
                        <x-choices-offline label="Kategori" wire:model="kategori_id" :options="$kategori" option-value="id"
                            option-label="name" placeholder="Pilih Kategori" single searchable />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="col-span-2">
                            <x-input label="Deskripsi Pendapatan" wire:model="name"
                                placeholder="Contoh: Upah kirim jagung" />
                        </div>
                        <x-select label="Metode Pembayaran" wire:model="bayar" :options="$bayars"
                            placeholder="Pilih Metode" />
                        <x-input label="Total Pendapatan" wire:model.live="total" prefix="Rp " money="IDR" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Cancel" link="/lainnya" />
            <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>

    </x-form>
</div>
