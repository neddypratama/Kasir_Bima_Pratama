<?php

use Livewire\Volt\Component;
use App\Models\{Barang, Client, Transaksi, DetailTransaksi, KonversiSatuan};
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;

new class extends Component {
    use Toast;

    public Transaksi $transaksi;
    public Transaksi $hpp;

    #[Rule('required')]
    public ?string $invoice = null;

    #[Rule('required')]
    public ?string $tanggal = null;

    #[Rule('required')]
    public ?int $client_id = null;

    #[Rule('required')]
    public float $total = 0;

    #[Rule('required')]
    public ?float $uang = 0;

    #[Rule('required')]
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
        ];
    }

    /* =====================
        MOUNT
    ====================== */
    public function mount(Transaksi $transaksi): void
    {
        $this->transaksi = $transaksi->load('details.barang');
        $this->invoice = $transaksi->invoice;
        $this->tanggal = $transaksi->tanggal;
        $this->client_id = $transaksi->client_id;
        $this->uang = $transaksi->uang;
        $this->barangs = Barang::all();

        // cari transaksi HPP pasangan
        $inv = substr($transaksi->invoice, -4);
        $tanggal = explode('-', $transaksi->invoice)[1];

        $this->hpp = Transaksi::where('invoice', 'like', "%-$tanggal-HPP-$inv")->firstOrFail();

        foreach ($transaksi->details as $detail) {
            $barang = $detail->barang;
            $satuan = KonversiSatuan::find($detail->satuan_id);

            // 🔥 stok awal = stok sekarang + qty lama × konversi lama
            $stokAwal = $barang->stok + $detail->kuantitas * $satuan->konversi;

            $this->details[] = [
                'barang_id' => $barang->id,
                'satuan' => $satuan->id,
                'value' => $detail->value,
                'kuantitas' => $detail->kuantitas,
                'stok_awal' => $stokAwal,
                'max_qty' => floor($stokAwal / $satuan->konversi),
                'satuans' => KonversiSatuan::where('barang_id', $barang->id)->get(),
            ];
        }

        $this->calculateTotal();
    }

    /* =====================
        DETAIL HANDLING
    ====================== */
    public function updatedDetails($value, $key): void
    {
        $index = explode('.', $key)[0];

        // pilih barang
        if (str_ends_with($key, '.barang_id')) {
            $barang = Barang::find($value);

            if ($barang) {
                $this->details[$index]['stok_awal'] = $barang->stok;
                $this->details[$index]['max_qty'] = $barang->stok;
                $this->details[$index]['kuantitas'] = 1;
                $this->details[$index]['value'] = 0;
                $this->details[$index]['satuan'] = null;
                $this->details[$index]['satuans'] = KonversiSatuan::where('barang_id', $barang->id)->get();
            }
        }

        // pilih satuan
        if (str_ends_with($key, '.satuan')) {
            $satuan = KonversiSatuan::find($value);

            if ($satuan) {
                $stokAwal = $this->details[$index]['stok_awal'];
                $this->details[$index]['max_qty'] = floor($stokAwal / $satuan->konversi);
                $this->details[$index]['value'] = $satuan->harga;
            }
        }

        // qty
        if (str_ends_with($key, '.kuantitas')) {
            $qty = max(1, (int) $value);
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
        SAVE UPDATE
    ====================== */
    public function save(): void
    {
        $this->validate([
            'client_id' => 'required',
            'details.*.barang_id' => 'required',
            'details.*.satuan' => 'required',
            'details.*.kuantitas' => 'required|min:1',
        ]);

        $client = Client::find($this->client_id);
        $status = $client->name == 'Quest' && $this->uang >= $this->total ? 'Lunas' : 'Hutang';

        /* =====================
            ROLLBACK STOK LAMA
        ====================== */
        foreach ($this->transaksi->details as $detail) {
            $satuan = KonversiSatuan::find($detail->satuan_id);
            $detail->barang->increment('stok', $detail->kuantitas * $satuan->konversi);
        }

        // hapus detail lama
        DetailTransaksi::where('transaksi_id', $this->transaksi->id)->delete();
        DetailTransaksi::where('transaksi_id', $this->hpp->id)->delete();

        $this->transaksi->update([
            'client_id' => $this->client_id,
            'total' => $this->total,
            'uang' => $this->uang,
            'status' => $status,
            'kembalian' => max(0, $this->uang - $this->total),
        ]);

        $totalHPP = 0;

        /* =====================
            SIMPAN DETAIL BARU
        ====================== */
        foreach ($this->details as $item) {
            $barang = Barang::find($item['barang_id']);
            $satuan = KonversiSatuan::find($item['satuan']);

            DetailTransaksi::create([
                'transaksi_id' => $this->transaksi->id,
                'barang_id' => $barang->id,
                'satuan_id' => $satuan->id,
                'value' => $item['value'],
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $item['value'] * $item['kuantitas'],
            ]);

            $barang->decrement('stok', $item['kuantitas'] * $satuan->konversi);
            $totalHPP += $barang->hpp * $item['kuantitas'];
        }

        $this->hpp->update(['total' => $totalHPP]);

        foreach ($this->details as $item) {
            $barang = Barang::find($item['barang_id']);
            $satuan = KonversiSatuan::find($item['satuan']);

            DetailTransaksi::create([
                'transaksi_id' => $this->hpp->id,
                'barang_id' => $barang->id,
                'satuan_id' => $satuan->id,
                'value' => $barang->hpp,
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $barang->hpp * $item['kuantitas'],
            ]);
        }

        $this->success('Transaksi berhasil diperbarui', redirectTo: '/kasir');
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
                                    :options="$item['satuans']" option-value="id" option-label="name"
                                    placeholder="Pilih Satuan" />

                                <x-input label="Harga Jual"
                                    value="Rp {{ number_format($item['value'] ?? 0, 0, '.', ',') }}" readonly />
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
