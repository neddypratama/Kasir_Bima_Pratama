<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\Kategori;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\Client;
use App\Models\StokBatch;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

    #[Rule('required')]
    public ?string $tanggal = null;

    #[Rule('required|array|min:1')]
    public array $details = [];

    public $barangs;

    public function with(): array
    {
        return [
            'barangs' => $this->barangs,
            'clients' => Client::where('name', 'like', '%Kandang Kambing%')->get(),
        ];
    }

    public function mount(): void
    {
        $this->user_id = auth()->id();
        $this->tanggal = now()->format('Y-m-d\TH:i:s');
        $this->updatedTanggal($this->tanggal);

        $this->barangs = Barang::all();

        $this->addDetail();
    }

    public function updatedTanggal($value): void
    {
        $tanggal = \Carbon\Carbon::parse($value)->format('Ymd');
        $rand = Str::upper(Str::random(4));

        $this->invoice = "INV-$tanggal-DPT-$rand";
        $this->invoice2 = "INV-$tanggal-HPP-$rand";
    }

    /* ===============================
       HITUNG HPP FIFO (REAL)
    =============================== */
    private function getHppPreview($barang_id, $qty)
    {
        $batches = StokBatch::where('barang_id', $barang_id)->where('qty_sisa', '>', 0)->orderBy('tanggal')->orderBy('id')->get();

        $sisa = $qty;
        $totalHpp = 0;

        foreach ($batches as $batch) {
            if ($sisa <= 0) {
                break;
            }

            $pakai = min($batch->qty_sisa, $sisa);
            $totalHpp += $pakai * $batch->harga;

            $sisa -= $pakai;
        }

        if ($qty > 0) {
            return $totalHpp / $qty; // rata-rata HPP
        }

        return 0;
    }

    private function getMaxQty($barang_id)
    {
        return StokBatch::where('barang_id', $barang_id)->sum('qty_sisa');
    }

    /* ===============================
       UPDATE DETAIL
    =============================== */
    public function updatedDetails($value, $key): void
    {
        $index = explode('.', $key)[0];

        // PILIH BARANG
        if (str_ends_with($key, '.barang_id')) {
            $max = $this->getMaxQty($value);

            $this->details[$index]['max_qty'] = $max;
            $this->details[$index]['kuantitas'] = 0.01;

            $this->details[$index]['value'] = $this->getHppPreview($value, 0.01);
        }

        // QTY
        if (str_ends_with($key, '.kuantitas')) {
            $qty = max(0.01, (float) $value);
            $max = $this->details[$index]['max_qty'] ?? 0;

            if ($qty > $max) {
                $qty = $max;
            }

            $this->details[$index]['kuantitas'] = $qty;

            $barang_id = $this->details[$index]['barang_id'] ?? null;

            if ($barang_id) {
                $this->details[$index]['value'] = $this->getHppPreview($barang_id, $qty);
            }
        }

        $this->calculateTotal();
    }

    private function calculateTotal(): void
    {
        $this->total = collect($this->details)->sum(fn($i) => ($i['value'] ?? 0) * ($i['kuantitas'] ?? 0));
    }

    /* ===============================
       SAVE TRANSAKSI
    =============================== */
    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            $penjualan = Transaksi::create([
                'invoice' => $this->invoice,
                'user_id' => $this->user_id,
                'tanggal' => $this->tanggal,
                'client_id' => $this->client_id,
                'type' => 'Kredit',
                'total' => $this->total,
                'status' => 'Lunas',
                'bayar' => 'Cash',
            ]);

            $totalHPP = 0;
            $hppPerBarang = [];

            foreach ($this->details as $item) {
                $barang = Barang::findOrFail($item['barang_id']);
                $qty = $item['kuantitas'];

                $kategoriJual = Kategori::where('name', 'like', 'Penjualan %' . $barang->jenis->name)->first();

                DetailTransaksi::create([
                    'transaksi_id' => $penjualan->id,
                    'barang_id' => $barang->id,
                    'kategori_id' => $kategoriJual->id ?? null,
                    'value' => $item['value'], // HPP per unit
                    'kuantitas' => $qty,
                    'sub_total' => $item['value'] * $qty,
                ]);

                // FIFO REAL
                $sisa = $qty;
                $hppBarang = 0;

                $batches = StokBatch::where('barang_id', $barang->id)->where('qty_sisa', '>', 0)->orderBy('tanggal')->orderBy('id')->lockForUpdate()->get();

                foreach ($batches as $batch) {
                    if ($sisa <= 0) {
                        break;
                    }

                    $pakai = min($batch->qty_sisa, $sisa);

                    $hpp = $pakai * $batch->harga;

                    $batch->decrement('qty_sisa', $pakai);

                    $hppBarang += $hpp;
                    $sisa -= $pakai;
                }

                if ($sisa > 0) {
                    throw new Exception("Stok tidak cukup untuk {$barang->name}");
                }

                $totalHPP += $hppBarang;

                $hppPerBarang[] = [
                    'barang_id' => $barang->id,
                    'qty' => $qty,
                    'total' => $hppBarang,
                ];
            }

            // TRANSAKSI HPP
            $hppTransaksi = Transaksi::create([
                'invoice' => $this->invoice2,
                'user_id' => $this->user_id,
                'tanggal' => $this->tanggal,
                'client_id' => $this->client_id,
                'type' => 'Debit',
                'total' => $totalHPP,
                'status' => 'Lunas',
                'bayar' => 'Cash',
            ]);

            foreach ($hppPerBarang as $barangId => $data) {
                $barang = Barang::find($data['barang_id']);
                
                $kategoriHpp = Kategori::where('name', 'like', 'HPP %' . $barang->jenis->name)->first();

                DetailTransaksi::create([
                    'transaksi_id' => $hppTransaksi->id,
                    'barang_id' => $barang->id,
                    'kategori_id' => $kategoriHpp->id,
                    'value' => $data['total'] / $data['qty'], // HPP per unit FIFO
                    'kuantitas' => $data['qty'],
                    'sub_total' => $data['total'], // TOTAL HPP BARANG
                ]);
            }
        });

        $this->success('Transaksi berhasil!', redirectTo: '/bon-kandang');
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'barang_id' => null,
            'value' => 0,
            'kuantitas' => 0.01,
            'max_qty' => 0,
        ];
    }

    public function removeDetail($index): void
    {
        unset($this->details[$index]);
        $this->details = array_values($this->details);
        $this->calculateTotal();
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Tambah Transaksi Piutang Kandang" separator progress-indicator />

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
                                    </x-choices-offline>
                                </div>
                                <x-input label="Satuan" placeholder="Kg" readonly />
                                <x-input label="Harga Jual"
                                    value="Rp {{ number_format($item['value'] ?? 0, 0, '.', ',') }}" readonly />
                                <x-input label="Qty (Max {{ $item['max_qty'] ?? '-' }})" type="number" min="0.1"
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
                    <div class="border-t pt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input label="Total Pembayaran" value="Rp {{ number_format($total, 0, '.', ',') }}" readonly
                            class="font-bold text-lg" />
                    </div>

                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Cancel" link="/bon-kandang" />
            <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>

    </x-form>
</div>
