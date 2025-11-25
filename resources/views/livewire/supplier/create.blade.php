<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\Client;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required|unique:transaksis,invoice')]
    public string $invoice = '';

    #[Rule('required')]
    public ?int $user_id = null;

    #[Rule('required|exists:clients,id')]
    public ?int $client_id = null; // supplier

    #[Rule('required|numeric|min:0')]
    public float $total = 0;

    #[Rule('required|numeric|min:0')]
    public float $uang = 0; // uang dibayarkan

    // kekurangan akan dihitung otomatis
    public float $kekurangan = 0;

    #[Rule('required')]
    public ?string $tanggal = null;

    public array $details = [];
    public $barangs;

    public function with(): array
    {
        // Ambil hanya supplier (keterangan mengandung Supplier)
        return [
            'barangs' => $this->barangs,
            'clients' => Client::where('keterangan', 'like', '%Supplier%')->get(),
        ];
    }

    public function mount(): void
    {
        $this->user_id = auth()->id();
        $this->tanggal = now()->format('Y-m-d\TH:i');
        $this->updatedTanggal($this->tanggal);

        $this->barangs = Barang::all();
        $this->addDetail(); // minimal 1 row
    }

    public function updatedTanggal($value): void
    {
        if ($value) {
            $tanggal = \Carbon\Carbon::parse($value)->format('Ymd');
            $str = Str::upper(Str::random(4));
            $this->invoice = 'PB-' . $tanggal . '-PGN-' . $str; // PB = Pembelian
        }
    }

    public function updatedDetails($value, $key): void
    {
        // ketika memilih barang -> isi harga beli otomatis dari field harga
        if (str_ends_with($key, '.barang_id')) {
            $index = explode('.', $key)[0];
            $barang = Barang::find($value);

            if ($barang) {
                $this->details[$index]['max_qty'] = null; // untuk pembelian tidak perlu max stok
                $this->details[$index]['kuantitas'] = max(1, $this->details[$index]['kuantitas'] ?? 1);
                $this->details[$index]['value'] = $barang->harga; // harga barang sebagai default harga beli
            }
        }

        // update qty -> pastikan >=1
        if (str_ends_with($key, '.kuantitas')) {
            $index = explode('.', $key)[0];
            $qty = max(1, (int) ($value ?: 1));
            $this->details[$index]['kuantitas'] = $qty;
        }

        // update harga manual (jika diubah)
        // recalc total
        $this->calculateTotal();
    }

    private function calculateTotal(): void
    {
        $this->total = collect($this->details)->sum(fn($d) => (float) ($d['value'] ?? 0) * (int) ($d['kuantitas'] ?? 1));
        $this->kekurangan = max(0, $this->total - $this->uang);
    }

    public function updatedUang($value): void
    {
        // saat uang diubah, hitung ulang kekurangan
        $this->kekurangan = max(0, $this->total - ($value ?: 0));
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

    public function save(): void
    {
        $this->validate([
            'client_id' => 'required|exists:clients,id',
            'details' => 'required|array|min:1',
            'details.*.barang_id' => 'required|exists:barangs,id',
            'details.*.value' => 'required|numeric|min:0',
            'details.*.kuantitas' => 'required|numeric|min:1',
            'uang' => 'required|numeric|min:0',
        ]);

        // hitung kekurangan & status
        $this->kekurangan = max(0, $this->total - $this->uang);
        $status = $this->kekurangan > 0 ? 'Hutang' : 'Lunas';

        // simpan transaksi pembelian
        $pembelian = Transaksi::create([
            'invoice' => $this->invoice,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'type' => 'Debit',
            'total' => $this->total,
            'uang' => $this->uang,
            'kembalian' => $this->kekurangan,
            'status' => $status,
        ]);

        // simpan detail + tambahkan stok
        foreach ($this->details as $item) {
            DetailTransaksi::create([
                'transaksi_id' => $pembelian->id,
                'barang_id' => $item['barang_id'],
                'value' => $item['value'],
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $item['value'] * $item['kuantitas'],
            ]);

            // tambahkan stok (karena pembelian)
            Barang::find($item['barang_id'])->increment('stok', $item['kuantitas']);
        }

        $this->success('Pembelian stok berhasil disimpan!', redirectTo: '/supplier');
    }
};

?>

<!-- Blade / Volt view -->
<div class="p-4 space-y-6">
    <x-header title="Tambah Pembelian Stok" separator progress-indicator />

    <x-form wire:submit="save">
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Informasi" subtitle="Pembelian ke Supplier" size="text-2xl" />
                </div>
                <div class="col-span-6 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input label="Invoice" wire:model="invoice" readonly />
                        <x-datetime label="Tanggal" wire:model="tanggal" type="datetime-local" />
                        <x-choices-offline label="Supplier" wire:model.live="client_id" :options="$clients"
                            option-value="id" option-label="name" placeholder="Pilih Supplier" single searchable />
                    </div>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Detail Barang" subtitle="Tambah barang yang dibeli" size="text-2xl" />
                </div>

                <div class="col-span-6 space-y-4">
                    @foreach ($details as $index => $item)
                        <div class="rounded-xl space-y-3 ">
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div class="col-span-3">
                                    <x-choices-offline wire:model.live="details.{{ $index }}.barang_id"
                                        :options="$barangs" option-value="id" option-label="name"
                                        placeholder="Pilih Barang" single searchable label="Barang" />
                                </div>

                                <x-input label="Qty" type="number" min="1"
                                    wire:model.lazy="details.{{ $index }}.kuantitas" />
                                <div class="col-span-2">
                                    <x-input label="Harga Beli" wire:model.live="details.{{ $index }}.value"
                                        prefix="Rp " money="IDR" />
                                </div>
                                <div class="col-span-2">
                                    <x-input label="Subtotal"
                                        value="Rp {{ number_format(($item['value'] ?? 0) * ($item['kuantitas'] ?? 1), 0, ',', '.') }}"
                                        readonly />
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <x-button wire:click="removeDetail({{ $index }})" icon="o-trash" label="Hapus"
                                    class="btn-error btn-sm" />
                            </div>
                        </div>
                    @endforeach

                    <x-button icon="o-plus" class="btn-primary" wire:click="addDetail" label="Tambah Item" />

                    <div class="border-t pt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input label="Total Pembelian" value="Rp {{ number_format($total, 0, ',', '.') }}" readonly
                            class="font-bold text-lg" />

                        <x-input label="Uang Dibayarkan" wire:model.live="uang" prefix="Rp " money
                            class="font-bold text-lg" />

                        <x-input label="Kekurangan" value="Rp {{ number_format($kekurangan, 0, ',', '.') }}" readonly
                            class="font-bold text-lg" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Batal" link="/supplier" />
            <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
