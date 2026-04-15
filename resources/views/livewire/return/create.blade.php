<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\StokBatch;
use App\Models\DetailTransaksi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public $transaksis;
    public $transaksis_id;
    public $transaksi;
    public $hpp;

    public $details = [];

    public $invoice;
    public $tanggal;
    public $name;
    public $total = 0;

    public $user_id;
    public $client_id;

    /* ===================== */
    public function mount()
    {
        $this->tanggal = now()->format('Y-m-d\TH:i:s');

        $this->transaksis = Transaksi::where('type', 'Kredit')->where('status', '!=', 'Batal')->get()->map(
            fn($t) => [
                'id' => $t->id,
                'name' => $t->invoice . ' - ' . \Carbon\Carbon::parse($t->tanggal)->format('d-m-Y H:i:s'),
            ],
        );
    }

    /* ===================== */
    public function updatedTransaksisId($value): void
    {
        if (!$value) {
            return;
        }

        $transaksi = Transaksi::with('details')->findOrFail($value);

        $this->transaksi = $transaksi;
        $this->user_id = auth()->id();
        $this->client_id = $transaksi->client_id;
        $this->name = 'Retur dari ' . $transaksi->invoice;

        // 🔥 generate invoice retur
        $str = substr($transaksi->invoice, -4);
        $tanggal = now()->format('Ymd');

        $this->hpp = Transaksi::with('details')->where('invoice', 'like', "%-$tanggal-HPP-$str")->first();
        $this->invoice = 'INV-' . $tanggal . '-RTN-' . Str::upper(Str::random(4));

        $this->tanggal = now()->format('Y-m-d\TH:i:s');

        $this->details = [];

        foreach ($transaksi->details as $detail) {
            /** 🔥 TOTAL RETUR SEBELUMNYA */
            $qtyRetur = DetailTransaksi::whereHas('transaksi', function ($q) use ($transaksi) {
                $q->where('type', 'Debit')->where('transaksi_id', $transaksi->id);
            })
                ->where('barang_id', $detail->barang_id)
                ->sum('kuantitas');

            $max = $detail->kuantitas - $qtyRetur;

            if ($max <= 0) {
                continue;
            }

            $this->details[] = [
                'barang_id' => $detail->barang_id,
                'kategori_id' => $detail->kategori_id,
                'value' => $detail->value,
                'kuantitas' => 0,
                'max_qty' => $max,
            ];
        }

        $this->calculateTotal();
    }

    /* ===================== */
    public function updatedDetails($value, $key)
    {
        $index = explode('.', $key)[0];

        if (str_contains($key, 'kuantitas')) {
            $qty = max(0, (float) $value);
            $max = $this->details[$index]['max_qty'];

            $this->details[$index]['kuantitas'] = min($qty, $max);
        }

        $this->calculateTotal();
    }

    /* ===================== */
    public function calculateTotal()
    {
        $this->total = collect($this->details)->sum(fn($i) => $i['value'] * $i['kuantitas']);
    }

    /* ===================== */
    public function save()
{
    DB::transaction(function () {

        /** =========================
         * TRANSAKSI RETUR
         ========================== */
        $retur = Transaksi::create([
            'invoice' => $this->invoice,
            'name' => $this->name,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'user_id' => $this->user_id,
            'type' => 'Debit',
            'total' => $this->total,
            'status' => 'Lunas',
        ]);

        $dataHPP = [];
        $totalHPP = 0;

        foreach ($this->details as $item) {

            if ($item['kuantitas'] <= 0) continue;

            /** =========================
             * SIMPAN DETAIL RETUR
             ========================== */
            $detail = DetailTransaksi::create([
                'transaksi_id' => $retur->id,
                'barang_id' => $item['barang_id'],
                'kategori_id' => $item['kategori_id'],
                'value' => $item['value'],
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $item['kuantitas'] * $item['value'],
            ]);

            /** =========================
             * BALIKKAN STOK (BATCH BARU)
             ========================== */
            StokBatch::create([
                'barang_id' => $item['barang_id'],
                'user_id' => $this->user_id,
                'detail_transaksi_id' => $detail->id,
                'tanggal' => $this->tanggal,
                'qty_masuk' => $item['kuantitas'],   // ✅ FIX
                'qty_sisa' => $item['kuantitas'],    // ✅ FIX
                'harga' => $item['value'],           // ✅ FIX (per unit)
            ]);

            /** =========================
             * HPP
             ========================== */
            $dataHPP[] = [
                'barang_id' => $item['barang_id'],
                'kategori_id' => $item['kategori_id'],
                'value' => $item['value'], // asumsi HPP = harga jual (simple mode)
                'kuantitas' => $item['kuantitas'],
            ];

            $totalHPP += $item['value'] * $item['kuantitas'];
        }

        /** =========================
         * TRANSAKSI HPP
         ========================== */
        $hpp = Transaksi::create([
            'invoice' => 'HPP-' . $this->invoice,
            'name' => 'HPP Retur ' . $this->invoice,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'user_id' => $this->user_id,
            'type' => 'Kredit',
            'total' => $totalHPP,
            'status' => 'Lunas',
        ]);

        foreach ($dataHPP as $value) {

            DetailTransaksi::create([
                'transaksi_id' => $hpp->id,
                'barang_id' => $value['barang_id'],
                'kategori_id' => $value['kategori_id'],
                'value' => $value['value'],
                'kuantitas' => $value['kuantitas'],
                'sub_total' => $value['kuantitas'] * $value['value'],
            ]);
        }
    });

    $this->success('Retur berhasil', redirectTo: '/return');
}
};
?>
<div class="p-4 space-y-6">

    <x-header title="Create Retur Penjualan" separator progress-indicator />

    <x-form wire:submit="save">

        <x-card>
            <div class="grid lg:grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Pilih Transaksi" subtitle="Pilih transaksi penjualan terlebih dahulu"
                        size="text-2xl" />
                </div>

                <div class="col-span-6">
                    <x-choices-offline wire:model.live="transaksis_id" label="Transaksi" :options="$transaksis"
                        placeholder="Pilih Transaksi Penjualan" single searchable clearable />
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="grid lg:grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Basic Info" subtitle="Buat transaksi baru" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input label="Invoice" wire:model="invoice" readonly />
                        <x-input label="User" :value="auth()->user()->name" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" icon="o-calendar" type="datetime-local"
                            step="1" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input label="Keterangan" wire:model="name" />
                        <x-input label="Client" :value="$transaksi?->client->name ?? '-'" readonly />
                    </div>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Detail Items" subtitle="Tambah barang ke transaksi" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    @foreach ($details as $i => $item)
                        <div class="grid grid-cols-4 gap-3 mb-3">

                            <x-input label="Barang" :value="\App\Models\Barang::find($item['barang_id'])->name ?? '-'" readonly />

                            <x-input label="Harga" :value="number_format($item['value'], 0, ',', '.')" prefix="Rp" readonly />

                            <x-input label="Qty (max {{ $item['max_qty'] }})"
                                wire:model.lazy="details.{{ $i }}.kuantitas" type="number" min="0.00"
                                step="0.01" />

                            <x-input label="Total" :value="number_format($item['value'] * $item['kuantitas'], 0, ',', '.')" prefix="Rp" readonly />

                        </div>
                    @endforeach

                    <div class="gap-3 border-t pt-4">
                        <x-input label="Total Pembayaran" :value="'Rp ' . number_format($total, 0, ',', '.')" readonly class="max-w-xs" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Cancel" link="/telur-return" />
            <x-button label="Save" type="submit" class="btn-primary" />
        </x-slot:actions>

    </x-form>
</div>
