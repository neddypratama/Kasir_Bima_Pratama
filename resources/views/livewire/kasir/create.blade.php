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
    public string $invoice2 = '';

    #[Rule('required')]
    public ?int $user_id = null;

    #[Rule('required')]
    public ?int $client_id = null;

    public float $total = 0;

    public ?float $uang = 0;

    #[Rule('required')]
    public ?string $tanggal = null;

    #[Rule('required')]
    public ?string $bayar = null;

    #[Rule('required|array|min:1')]
    public array $details = [];

    public $barangs;

    /* =====================
        WITH
    ====================== */
    public function with(): array
    {
        return [
            'barangs' => $this->barangs,
            'clients' => Client::where('keterangan', 'like', '%Pembeli%')->get(),
            'satuan' => [['id' => 'Eceran', 'name' => 'Eceran'], ['id' => 'Partai', 'name' => 'Partai']],
            'bayars' => [['id' => 'Cash', 'name' => 'Cash'], ['id' => 'Transfer', 'name' => 'Transfer']],
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

        $this->barangs = Barang::all();

        $quest = Client::where('name', 'like', '%Quest%')->first();
        $this->client_id = $quest?->id;

        $this->addDetail();
    }

    public function updatedTanggal($value): void
    {
        $tanggal = \Carbon\Carbon::parse($value)->format('Ymd');
        $rand = Str::upper(Str::random(4));

        $this->invoice = "INV-$tanggal-DPT-$rand";
        $this->invoice2 = "INV-$tanggal-HPP-$rand";
    }

    /* =====================
        DETAIL HANDLING
    ====================== */
    public function updatedDetails($value, $key): void
    {
        $index = explode('.', $key)[0];

        /*
    |--------------------------------------------------------------------------
    | PILIH BARANG
    |--------------------------------------------------------------------------
    */
        if (str_ends_with($key, '.barang_id')) {
            $barang = Barang::find($value);

            if ($barang) {
                $this->details[$index]['max_qty'] = $barang->stok;
                $this->details[$index]['kuantitas'] = 1;

                if ($this->details[$index]['satuan'] == 'Eceran') {
                    $this->details[$index]['value'] = $barang->harga_eceran;
                } else {
                    $this->details[$index]['value'] = $barang->harga_sak;
                }
            }
        }

        if (str_ends_with($key, '.satuan')) {
            $barang = Barang::find($this->details[$index]['barang_id']);

            if ($barang) {
                if ($this->details[$index]['satuan'] == 'Eceran') {
                    $this->details[$index]['value'] = $barang->harga_eceran;
                } else {
                    $this->details[$index]['value'] = $barang->harga_sak;
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | QTY
    |--------------------------------------------------------------------------
    */
        if (str_ends_with($key, '.kuantitas')) {
            $qty = max(0.01, (float) str_replace(',', '.', $value));
            $max = $this->details[$index]['max_qty'] ?? $qty;

            $this->details[$index]['kuantitas'] = min($qty, $max);
        }

        $this->calculateTotal();
    }

    private function calculateTotal(): void
    {
        $this->total = collect($this->details)->sum(fn($i) => ($i['value'] ?? 0) * ($i['kuantitas'] ?? 1));
    }

    /* =====================
        SAVE
    ====================== */
    public function save(): void
    {
        $this->validate([
            'client_id' => 'required',
            'details' => 'required|array|min:1',
            'details.*.barang_id' => 'required|exists:barangs,id',
            'details.*.satuan' => 'required',
            'details.*.kuantitas' => 'required|numeric|min:1',
        ]);

        $client = Client::find($this->client_id);
        $status = $client->name == 'Quest' && $this->uang >= $this->total ? 'Lunas' : 'Hutang';

        $kasir = Transaksi::create([
            'invoice' => $this->invoice,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'type' => 'Kredit',
            'total' => $this->total,
            'status' => $status,
            'bayar' => $this->bayar,
            'uang' => $this->uang,
            'kembalian' => max(0, $this->uang - $this->total),
        ]);

        $totalHPP = 0;

        foreach ($this->details as $item) {
            $barang = Barang::find($item['barang_id']);
            $kategori = Kategori::where('name', 'like', 'Penjualan %' . $barang->jenis->name)->first();

            DetailTransaksi::create([
                'transaksi_id' => $kasir->id,
                'barang_id' => $barang->id,
                'kategori_id' => $kategori->id,
                'value' => $item['value'],
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $item['value'] * $item['kuantitas'],
            ]);

            $totalHPP += $barang->hpp * $item['kuantitas'];
            $barang->decrement('stok', $item['kuantitas']);
        }

        $hpp = Transaksi::create([
            'invoice' => $this->invoice2,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'type' => 'Debit',
            'total' => $totalHPP,
            'status' => $status,
            'bayar' => $this->bayar,
        ]);

        foreach ($this->details as $item) {
            $barang = Barang::find($item['barang_id']);
            $kategori = Kategori::where('name', 'like', 'HPP %' . $barang->jenis->name)->first();

            DetailTransaksi::create([
                'transaksi_id' => $hpp->id,
                'barang_id' => $barang->id,
                'kategori_id' => $kategori->id,
                'value' => $barang->hpp,
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $barang->hpp * $item['kuantitas'],
            ]);
        }

        $this->success('Transaksi berhasil dibuat!', redirectTo: '/kasir');
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'barang_id' => null,
            'satuan' => null,
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
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                                            {{ $barangs->name }}
                                        @endscope
                                    </x-choices-offline>
                                </div>
                                <x-select label="Satuan" wire:model.live="details.{{ $index }}.satuan"
                                    :options="$satuan" placeholder="Pilih Satuan" />
                                <x-input label="Harga Jual"
                                    value="Rp {{ number_format($item['value'] ?? 0, 0, '.', ',') }}" readonly />
                                <x-input label="Qty (Max {{ $item['max_qty'] ?? '-' }})" type="number" min="1"
                                    step="0.01" wire:model.lazy="details.{{ $index }}.kuantitas" />
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
                    <div class="border-t pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <x-input label="Total Pembayaran" value="Rp {{ number_format($total, 0, '.', ',') }}" readonly
                            class="font-bold text-lg" />

                        <x-select label="Metode Pembayaran" wire:model="bayar" :options="$bayars"
                            placeholder="Pilih Metode" />

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
