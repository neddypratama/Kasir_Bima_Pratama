<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
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
    public string $invoice2 = '';

    #[Rule('required')]
    public ?int $user_id = null;

    #[Rule('required')]
    public ?int $client_id = null;

    #[Rule('required|numeric|min:1')]
    public float $total = 0;

    #[Rule('nullable|numeric|min:1')]
    public ?float $uang = 0; // uang diterima dari pelanggan

    #[Rule('required')]
    public ?string $tanggal = null;

    public array $details = [];

    public $barangs;

    public function with(): array
    {
        return [
            'barangs' => $this->barangs,
            'clients' => Client::where('keterangan', 'like', '%Pembeli%')->get(),
        ];
    }

    public function mount(): void
    {
        $this->user_id = auth()->id();
        $this->tanggal = now()->format('Y-m-d\TH:i');
        $this->updatedTanggal($this->tanggal);

        $this->barangs = Barang::all();
        $quest = Client::where('name', 'like', '%Quest%')->first();
        $this->client_id = $quest ? $quest->id : null;
        $this->addDetail();
    }

    public function updatedTanggal($value): void
    {
        if ($value) {
            $tanggal = \Carbon\Carbon::parse($value)->format('Ymd');
            $str = Str::upper(Str::random(4));
            $this->invoice = 'INV-' . $tanggal . '-DPT-' . $str;
            $this->invoice2 = 'INV-' . $tanggal . '-HPP-' . $str;
        }
    }

    public function updatedDetails($value, $key): void
    {
        // Ketika memilih barang
        if (str_ends_with($key, '.barang_id')) {
            $index = explode('.', $key)[0];
            $barang = Barang::find($value);

            if ($barang) {
                $this->details[$index]['max_qty'] = $barang->stok;
                $this->details[$index]['kuantitas'] = 1;
                $this->details[$index]['value'] = $barang->harga; // auto harga
            }
        }

        // Update qty
        if (str_ends_with($key, '.kuantitas')) {
            $index = explode('.', $key)[0];
            $qty = max(1, $value);
            $maxQty = $this->details[$index]['max_qty'] ?? null;

            if ($maxQty !== null && $qty > $maxQty) {
                $qty = $maxQty;
            }

            $this->details[$index]['kuantitas'] = $qty;
        }

        // Hitung total
        $this->calculateTotal();
    }

    private function calculateTotal(): void
    {
        $this->total = collect($this->details)->sum(function ($item) {
            return ($item['value'] ?? 0) * ($item['kuantitas'] ?? 1);
        });
    }

    public function save(): void
    {
        $this->validate([
            'client_id' => 'required',
            'details' => 'required|array|min:1',
            'details.*.barang_id' => 'required|exists:barangs,id',
            'details.*.value' => 'required|numeric|min:0',
            'details.*.kuantitas' => 'required|numeric|min:1',
        ]);

        // Validasi stok
        foreach ($this->details as $i => $item) {
            $barang = Barang::find($item['barang_id']);

            if ($item['kuantitas'] > $barang->stok) {
                $this->addError("details.$i.kuantitas", 'Qty melebihi stok barang.');
                return;
            }
        }

        $client = Client::find($this->client_id);
        $status = '';
        if ($client->name == 'Quest' && $this->uang > $this->total) {
            $status = 'Lunas';
        } else {
            $status = 'Hutang';
        }
        // Simpan transaksi
        $kasir = Transaksi::create([
            'invoice' => $this->invoice,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'type' => 'Kredit',
            'total' => $this->total,
            'status' => $status,
            'uang' => $this->uang,
            'kembalian' => max(0, $this->uang - $this->total),
        ]);

        $totalHPP = 0;
        // Simpan detail + kurangi stok
        foreach ($this->details as $item) {
            DetailTransaksi::create([
                'transaksi_id' => $kasir->id,
                'barang_id' => $item['barang_id'],
                'value' => $item['value'],
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $item['value'] * $item['kuantitas'],
            ]);
            $totalHPP += Barang::find($item['barang_id'])->hpp * $item['kuantitas'];
            Barang::find($item['barang_id'])->decrement('stok', $item['kuantitas']);
        }

        $hpp = Transaksi::create([
            'invoice' => $this->invoice2,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'type' => 'Debit',
            'total' => $totalHPP,
            'status' => $status,
            'uang' => null,
            'kembalian' => null,
        ]);

        // Simpan detail + kurangi stok
        foreach ($this->details as $item) {
            DetailTransaksi::create([
                'transaksi_id' => $hpp->id,
                'barang_id' => $item['barang_id'],
                'value' => Barang::find($item['barang_id'])->hpp,
                'kuantitas' => $item['kuantitas'],
                'sub_total' => Barang::find($item['barang_id'])->hpp * $item['kuantitas'],
            ]);
        }

        $this->success('Transaksi berhasil dibuat!', redirectTo: '/kasir');
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'barang_id' => null,
            'value' => 0,
            'kuantitas' => 1,
            'max_qty' => null,
        ];
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
    <x-header title="Tambah Transaksi Penjualan" separator progress-indicator />

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
                        <x-choices-offline label="Client" wire:model="client_id" :options="$clients" option-value="id"
                            option-label="name" placeholder="Pilih Client" single searchable />
                    </div>

                </div>
            </div>
        </x-card>

        <!-- DETAIL ITEMS -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Detail Barang" subtitle="Pilih barang" size="text-2xl" />
                </div>

                <div class="col-span-6 space-y-4">

                    @foreach ($details as $index => $item)
                        <div class="rounded-xl space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div class="col-span-2">
                                    <x-choices-offline placeholder="Pilih Barang"
                                        wire:model.live="details.{{ $index }}.barang_id" :options="$barangs" single
                                        searchable clearable label="Barang">
                                        {{-- Tampilan item di dropdown --}} @scope('item', $barangs)
                                            <x-list-item :item="$barangs">
                                            </x-list-item>
                                        @endscope

                                        {{-- Tampilan ketika sudah dipilih --}}
                                        @scope('selection', $barangs)
                                            {{ $barangs->name . ' | Rp ' . number_format($barangs->harga, 0, '.', ',') }}
                                        @endscope
                                    </x-choices-offline>
                                </div>

                                <x-input label="Qty (Max {{ $item['max_qty'] ?? '-' }})" type="number" min="1"
                                    wire:model.lazy="details.{{ $index }}.kuantitas" />
                                <x-input label="Total Item"
                                    value="Rp {{ number_format(($item['value'] ?? 0) * ($item['kuantitas'] ?? 1), 0, '.', ',') }}"
                                    readonly />

                            </div>

                            <div class="flex justify-end">
                                <x-button wire:click="removeDetail({{ $index }})" icon="o-trash" label="Hapus"
                                    class="btn-error btn-sm" />
                            </div>
                        </div>
                    @endforeach

                    <x-button icon="o-plus" class="btn-primary" wire:click="addDetail" label="Tambah Item" />

                    <!-- TOTAL, UANG, KEMBALIAN -->
                    <div class="border-t pt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <x-input label="Total Pembayaran" value="Rp {{ number_format($total, 0, '.', ',') }}" readonly
                            class="font-bold text-lg" />

                        <x-input label="Uang Diterima" wire:model.live="uang" prefix="Rp " money
                            class="font-bold text-lg" />

                        <x-input label="Kembalian" value="Rp {{ number_format(max(0, $uang - $total), 0, '.', ',') }}"
                            readonly class="font-bold text-lg" />
                    </div>

                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Cancel" link="/kasir" />
            <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>

    </x-form>
</div>
