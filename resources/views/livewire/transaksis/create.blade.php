<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Client;
use App\Models\User;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required|unique:transaksis,invoice')]
    public string $invoice = '';

    #[Rule('required')]
    public string $name = '';

    #[Rule('required|integer|min:1')]
    public int $total = 0;

    #[Rule('required')]
    public ?int $user_id = null;

    public ?string $tanggal = null;

    #[Rule('required|array|min:1')]
    public array $details = [];

    public $bagianOptions = [
        ['id' => 'Aset', 'name' => 'Aset'],
        ['id' => 'Liabilitas', 'name' => 'Liabilitas'],
        ['id' => 'Pendapatan', 'name' => 'Pendapatan'],
        ['id' => 'Pengeluaran', 'name' => 'Pengeluaran']
    ];

    public function with(): array
    {
        return [
            'users' => User::all(),
            'barangs' => Barang::all(),
            'kategoris' => Kategori::all(),
            'clients' => Client::all(),
        ];
    }

    public function updatedTanggal($value): void
    {
        if ($value) {
            $tanggal = \Carbon\Carbon::parse($value)->format('Ymd');
            $this->invoice = 'INV-' . $tanggal . '-' . Str::upper(Str::random(6));
        }
    }

    public function updatedDetails($value, $key): void
    {
        if (str_ends_with($key, '.value') || str_ends_with($key, '.kuantitas')) {
            $this->calculateTotal();
        }
    }

    private function calculateTotal(): void
    {
        $this->total = collect($this->details)
            ->sum(fn($item) => ((int) ($item['value'] ?? 0)) * ((int) ($item['kuantitas'] ?? 1)));
    }

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d\TH:i');
        $this->updatedTanggal($this->tanggal);
    }

    public function save(): void
    {
        $this->validate();

        $transaksi = Transaksi::create([
            'invoice' => $this->invoice,
            'name' => $this->name,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'total' => $this->total,
        ]);

        foreach ($this->details as $item) {
            $this->validate([
                'details.*.type' => 'required|in:Kredit,Debit',
                'details.*.value' => 'required|numeric|min:0',
                'details.*.bagian' => 'required|in:Aset,Liabilitas,Pendapatan,Pengeluaran',
                'details.*.barang_id' => 'nullable|exists:barangs,id',
                'details.*.kuantitas' => 'nullable|integer|min:1',
                'details.*.client_id' => 'nullable|exists:clients,id',
                'details.*.kategori_id' => 'nullable|exists:kategoris,id',
            ]);

            DetailTransaksi::create([
                'transaksi_id' => $transaksi->id,
                'type' => $item['type'],
                'value' => (int) $item['value'],
                'bagian' => $item['bagian'],
                'barang_id' => $item['barang_id'] ?? null,
                'kuantitas' => $item['kuantitas'] ?? null,
                'client_id' => $item['client_id'] ?? null,
                'kategori_id' => $item['kategori_id'] ?? null,
            ]);
        }

        $this->success('Transaksi berhasil dibuat!', redirectTo: '/transaksis');
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'type' => 'Kredit',
            'value' => 0,
            'bagian' => null,
            'barang_id' => null,
            'kuantitas' => 1,
            'client_id' => null,
            'kategori_id' => null,
        ];
        $this->calculateTotal();
    }

    public function removeDetail(int $index): void
    {
        unset($this->details[$index]);
        $this->details = array_values($this->details);
        $this->calculateTotal();
    }
};

?>

<div class="p-4 space-y-6">
    <x-header title="Create Transaksi" separator progress-indicator />

    <x-form wire:submit="save">
        <div class="lg:grid grid-cols-5">
            <div class="col-span-2">
                <x-header title="Basic Info" subtitle="Buat transaksi baru" size="text-2xl" />
            </div>
            <div class="col-span-3 grid gap-3">
                <x-input label="Invoice" wire:model="invoice" readonly />
                <x-input label="Name" wire:model="name" />
                <x-select label="User" wire:model="user_id" :options="$users" placeholder="---" />
                <x-datetime label="Date + Time" wire:model="tanggal" icon="o-calendar" type="datetime-local" />
            </div>
        </div>

        <hr class="my-5" />
        <div class="lg:grid grid-cols-5">
            <div class="col-span-2">
                <x-header title="Detail Items" subtitle="Tambah barang ke transaksi" size="text-2xl" />
            </div>
            <div class="col-span-3 grid gap-3">
                @foreach ($details as $index => $item)
                    <div class="grid grid-cols-3 gap-4 items-center">
                        <x-select wire:model.live="details.{{ $index }}.barang_id" label="Barang"
                            :options="$barangs" placeholder="Pilih Barang" />
                        <x-input label="Value" wire:model.live="details.{{ $index }}.value" prefix="Rp "
                            money="IDR" />
                        <x-input label="Qty" wire:model.live="details.{{ $index }}.kuantitas" type="number"
                            min="1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <x-select wire:model.live="details.{{ $index }}.client_id" label="Client"
                            :options="$clients" placeholder="Pilih Client" />
                        <x-select wire:model.live="details.{{ $index }}.type"
                            :options="[['name' => 'Kredit', 'id' => 'Kredit'], ['name' => 'Debit', 'id' => 'Debit']]"
                            label="Type" placeholder="Pilih Type" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <x-select wire:model.live="details.{{ $index }}.bagian" label="Bagian" :options="$bagianOptions"
                            placeholder="Pilih Bagian" />
                        <x-select wire:model.live="details.{{ $index }}.kategori_id" label="Kategori"
                            :options="$kategoris" placeholder="Pilih Kategori" />
                    </div>

                    <x-button spinner icon="o-trash" class="bg-red-500 text-white"
                        wire:click="removeDetail({{ $index }})" />
                @endforeach

                <x-button spinner icon="o-plus" label="Tambah Item" wire:click="addDetail" class="mt-3" />
                <x-input label="Total" :value="number_format($total, 0, '.', ',')" prefix="Rp" readonly />
            </div>
        </div>

        <x-slot:actions>
            <x-button spinner label="Cancel" link="/transaksis" />
            <x-button spinner label="Create" icon="o-paper-airplane" spinner="save" type="submit"
                class="btn-primary" />
        </x-slot:actions>
    </x-form>
</div>
