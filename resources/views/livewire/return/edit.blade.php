<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Kategori;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast;

    public Transaksi $transaksi;

    #[Rule('required')]
    public string $name = '';

    #[Rule('required')]
    public ?int $client_id = null;

    #[Rule('required')]
    public ?int $user_id = null;

    public ?string $tanggal = null;

    #[Rule('required|array|min:1')]
    public array $details = [];

    public float $total = 0;

    /**
     * 🔥 MOUNT EDIT RETUR
     */
    public function mount(Transaksi $transaksi): void
    {
        // 🔥 ambil invoice dari name
        preg_match('/Retur dari (.+)/', $transaksi->name, $matches);

        $parentInvoice = $matches[1] ?? null;

        // 🔥 cari transaksi asal
        $parent = Transaksi::with('details', 'client')->where('invoice', $parentInvoice)->first();

        if (!$parent) {
            abort(404, 'Transaksi asal tidak ditemukan');
        }

        // 🔥 load retur + parent
        $this->transaksi = $transaksi->load('details', 'client');

        $this->user_id = auth()->id();
        $this->client_id = $transaksi->client_id;
        $this->name = $transaksi->name;
        $this->tanggal = \Carbon\Carbon::parse($transaksi->tanggal)->format('Y-m-d\TH:i:s');

        $this->details = [];

        foreach ($transaksi->details as $detail) {
            // 🔥 ambil dari transaksi asal
            $parentDetail = $parent->details->firstWhere('barang_id', $detail->barang_id);

            $maxQty = $parentDetail?->kuantitas ?? 0;

            $this->details[] = [
                'barang_id' => $detail->barang_id,
                'kategori_id' => $detail->kategori_id,
                'value' => $detail->value,

                // qty dari retur
                'kuantitas' => $detail->kuantitas,

                // batas dari transaksi asal
                'max_qty' => $maxQty,
            ];
        }

        $this->calculateTotal();
    }

    /**
     * 🔥 VALIDASI REALTIME
     */
    public function updatedDetails($value, $key): void
    {
        if (str_ends_with($key, '.kuantitas')) {
            $index = explode('.', $key)[0];

            $qty = (float) $value;
            $max = $this->details[$index]['max_qty'];

            if ($qty > $max) {
                $this->details[$index]['kuantitas'] = $max;
                $this->warning("Qty tidak boleh melebihi {$max}");
            }
        }

        $this->calculateTotal();
    }

    /**
     * 🔥 HITUNG TOTAL
     */
    private function calculateTotal(): void
    {
        $this->total = collect($this->details)->sum(fn($d) => ($d['value'] ?? 0) * ($d['kuantitas'] ?? 0));
    }

    /**
     * 🔥 UPDATE RETUR
     */
    public function save(): void
    {
        $this->validate();

        if (collect($this->details)->sum('kuantitas') <= 0) {
            $this->warning('Minimal 1 item diretur');
            return;
        }

        foreach ($this->details as $item) {
            if ($item['kuantitas'] > $item['max_qty']) {
                $this->error('Qty melebihi transaksi asal');
                return;
            }
        }

        DB::transaction(function () {
            $this->transaksi->update([
                'name' => $this->name,
                'user_id' => $this->user_id,
                'client_id' => $this->client_id,
                'tanggal' => $this->tanggal,
                'total' => $this->total,
                'type' => 'Debit',
            ]);

            $this->transaksi->details()->delete();
            foreach ($this->details as $item) {
                DetailTransaksi::create([
                    'transaksi_id' => $this->transaksi->id,
                    'kategori_id' => $item['kategori_id'],
                    'barang_id' => $item['barang_id'],
                    'value' => $item['value'],
                    'kuantitas' => $item['kuantitas'],
                    'sub_total' => ($item['value'] ?? 0) * ($item['kuantitas'] ?? 1),
                ]);
            }

            $str = substr($this->transaksi->invoice, -4);
            $part = explode('-', $this->transaksi->invoice);
            $tanggal = $part[1];
            
            $invoiceHpp = 'INV-' . $tanggal . '-HPP-' . $str;
            $hpp = Transaksi::where('invoice', $invoiceHpp)->first();

            $hpp->update([
                'name' => $this->name,
                'user_id' => $this->user_id,
                'client_id' => $this->client_id,
                'tanggal' => $this->tanggal,
                'total' => $this->total,
                'type' => 'Kredit',
            ]);

            $hpp->details()->delete();
            foreach ($this->details as $item) {
                DetailTransaksi::create([
                    'transaksi_id' => $hpp->id,
                    'kategori_id' => $item,
                    'barang_id' => $item['barang_id'],
                    'value' => $item['value'],
                    'kuantitas' => $item['kuantitas'],
                    'sub_total' => ($item['value'] ?? 0) * ($item['kuantitas'] ?? 1),
                ]);
            }
        });

        $this->success('Retur berhasil diupdate!', redirectTo: '/return');
    }
};
?>

<div class="p-4 space-y-6">

    <x-header title="Edit Retur Penjualan" separator />

    <x-form wire:submit="save">

        {{-- 🔥 INFO --}}
        <x-card>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Invoice Retur" :value="$transaksi->invoice" readonly />
                <x-input label="Client" :value="$transaksi->client->name ?? '-'" readonly />
                <x-datetime label="Date + Time" wire:model="tanggal" icon="o-calendar" type="datetime-local"
                    step="1" />
                <x-input label="Keterangan" wire:model="name" />
            </div>
        </x-card>

        {{-- 🔥 DETAIL --}}
        <x-card>
            @foreach ($details as $i => $item)
                <div class="grid grid-cols-4 gap-3 mb-3">

                    <x-input label="Barang" :value="\App\Models\Barang::find($item['barang_id'])->name ?? '-'" readonly />

                    <x-input label="Harga" :value="number_format($item['value'], 0, ',', '.')" prefix="Rp" readonly />

                    @if ($this->transaksi->status == 'Selesai')
                        <x-input label="Qty (max {{ $item['max_qty'] }})"
                            wire:model.lazy="details.{{ $i }}.kuantitas" type="number" min="0"
                            step="0.01" readonly />
                    @else
                        <x-input label="Qty (max {{ $item['max_qty'] }})"
                            wire:model.lazy="details.{{ $i }}.kuantitas" type="number" min="0"
                            step="0.01" />
                    @endif

                    <x-input label="Total" :value="number_format($item['value'] * $item['kuantitas'], 0, ',', '.')" prefix="Rp" readonly />

                </div>
            @endforeach

            <div class="text-right font-bold text-lg border-t pt-3">
                Total: Rp {{ number_format($total, 0, ',', '.') }}
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Cancel" link="/return" />
            <x-button label="Update" type="submit" class="btn-primary" />
        </x-slot:actions>

    </x-form>
</div>
