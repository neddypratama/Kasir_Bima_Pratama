<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\Client;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;

new class extends Component {
    use Toast;

    public Transaksi $transaksi;

    #[Rule('required')]
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
        return [
            'barangs' => $this->barangs,
            'clients' => Client::where('keterangan', 'like', '%Supplier%')->get(),
        ];
    }

    public function mount(Transaksi $transaksi)
    {
        $this->transaksi = $transaksi->load(['details', 'details.barang']);
        $this->invoice = $transaksi->invoice;
        $this->user_id = $transaksi->user_id;
        $this->client_id = $transaksi->client_id;
        $this->tanggal = $transaksi->tanggal;
        $this->uang = $transaksi->uang;
        $this->total = $transaksi->total;
        $this->kekurangan = $transaksi->kembalian;

        $this->barangs = Barang::all();

        // load details existing
        foreach ($this->transaksi->details as $d) {
            $this->details[] = [
                'detail_id' => $d->id,
                'barang_id' => $d->barang_id,
                'value' => $d->value,
                'kuantitas' => $d->kuantitas,
            ];
        }
    }

    public function updatedDetails()
    {
        $this->calculateTotal();
    }

    public function updatedUang()
    {
        $this->kekurangan = max(0, $this->total - $this->uang);
    }

    private function calculateTotal()
    {
        $this->total = collect($this->details)->sum(fn($i) => ($i['value'] ?? 0) * ($i['kuantitas'] ?? 1));

        $this->kekurangan = max(0, $this->total - $this->uang);
    }

    public function addDetail()
    {
        $this->details[] = [
            'detail_id' => null,
            'barang_id' => null,
            'value' => 0,
            'kuantitas' => 1,
        ];
    }

    public function removeDetail($index)
    {
        // kembalikan stok lama (jika ada)
        if (isset($this->details[$index]['detail_id']) && $this->details[$index]['barang_id']) {
            $old = DetailTransaksi::find($this->details[$index]['detail_id']);
            if ($old) {
                Barang::find($old->barang_id)->decrement('stok', $old->kuantitas);
            }
        }

        unset($this->details[$index]);
        $this->details = array_values($this->details);
        $this->calculateTotal();
    }

    public function save()
    {
        // hitung status
        $this->kekurangan = max(0, $this->total - $this->uang);
        $status = $this->kekurangan > 0 ? 'Hutang' : 'Lunas';

        // update transaksi
        $this->transaksi->update([
            'client_id' => $this->client_id,
            'tanggal' => $this->tanggal,
            'uang' => $this->uang,
            'total' => $this->total,
            'kembalian' => $this->kekurangan,
            'status' => $status,
        ]);

        // update detail transaksi
        foreach ($this->details as $row) {
            // jika edit detail lama
            if ($row['detail_id']) {
                $detail = DetailTransaksi::find($row['detail_id']);

                // hitung selisih qty
                $selisih = $row['kuantitas'] - $detail->kuantitas;

                if ($selisih > 0) {
                    Barang::find($row['barang_id'])->increment('stok', $selisih);
                } elseif ($selisih < 0) {
                    Barang::find($row['barang_id'])->decrement('stok', abs($selisih));
                }

                $detail->update([
                    'barang_id' => $row['barang_id'],
                    'value' => $row['value'],
                    'kuantitas' => $row['kuantitas'],
                    'sub_total' => $row['value'] * $row['kuantitas'],
                ]);
            }

            // jika detail baru
            else {
                DetailTransaksi::create([
                    'transaksi_id' => $this->transaksi->id,
                    'barang_id' => $row['barang_id'],
                    'value' => $row['value'],
                    'kuantitas' => $row['kuantitas'],
                    'sub_total' => $row['value'] * $row['kuantitas'],
                ]);

                Barang::find($row['barang_id'])->increment('stok', $row['kuantitas']);
            }
        }

        $this->success('Pembelian berhasil diperbarui!', redirectTo: '/supplier');
    }
};

?>

<div class="p-4 space-y-6">
    <x-header title="Edit Pembelian" subtitle="Ubah pembelian ke supplier" separator />
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
