<?php

use Livewire\Volt\Component;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Stok;
use App\Models\StokBatch;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\User;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use Toast;

    public ?Stok $stokModel = null;

    #[Rule('required')]
    public ?string $invoice = null;

    #[Rule('required')]
    public ?int $barang_id = null;

    #[Rule('required')]
    public ?string $tanggal = null;

    public ?int $user_id = null;

    public float $stok = 0;
    public float $stokAsli = 0;

    public float $tambah = 0;
    public float $kurang = 0;
    public float $kotor = 0;
    public float $pecah = 0;

    /* =========================
        MOUNT
    ========================== */
    public function mount($stok): void
    {
        $this->stokModel = Stok::findOrFail($stok);

        $this->invoice = $this->stokModel->invoice;
        $this->barang_id = $this->stokModel->barang_id;
        $this->tanggal = Carbon::parse($this->stokModel->tanggal)->format('Y-m-d\TH:i:s');
        $this->user_id = $this->stokModel->user_id;

        $stokBatch = StokBatch::where('barang_id', $this->barang_id)->sum('qty_sisa');

        $this->stokAsli = $stokBatch - $this->stokModel->tambah + ($this->stokModel->kurang + $this->stokModel->kotor + $this->stokModel->rusak);

        $this->stok = $stokBatch;

        $this->tambah = $this->stokModel->tambah;
        $this->kurang = $this->stokModel->kurang;
        $this->kotor = $this->stokModel->kotor;
        $this->pecah = $this->stokModel->rusak;
    }

    public function with(): array
    {
        return [
            'barangs' => Barang::all(),
            'users' => User::all(),
        ];
    }

    public function updatedBarangId($id): void
    {
        if (!$id) {
            $this->stok = $this->stokAsli = 0;
            return;
        }

        // total stok batch saat ini
        $stokBatch = StokBatch::where('barang_id', $id)->sum('qty_sisa') ?? 0;

        // jika edit & barang sama
        if ($this->stokModel && $id == $this->stokModel->barang_id) {
            // kembalikan ke kondisi sebelum transaksi ini
            $this->stokAsli = $stokBatch - $this->stokModel->tambah + ($this->stokModel->kurang + $this->stokModel->kotor + $this->stokModel->rusak);

            // pakai nilai transaksi lama
            $this->tambah = $this->stokModel->tambah;
            $this->kurang = $this->stokModel->kurang;
            $this->kotor = $this->stokModel->kotor;
            $this->pecah = $this->stokModel->rusak;
        } else {
            // edit tapi ganti barang
            $this->stokAsli = $stokBatch;

            // reset input
            $this->tambah = 0;
            $this->kurang = 0;
            $this->kotor = 0;
            $this->pecah = 0;
        }

        // hitung stok akhir
        $this->stok = max(0, $this->stokAsli + $this->tambah - ($this->kurang + $this->kotor + $this->pecah));
    }

    public function updated($field): void
    {
        if (in_array($field, ['tambah', 'kurang', 'kotor', 'pecah'])) {
            $this->stok = max(0, $this->stokAsli + $this->tambah - ($this->kurang + $this->kotor + $this->pecah));
        }
    }

    /* =========================
        FIFO UNIVERSAL
    ========================== */
    private function fifo(
        int $barangId,
        float $qty,
        string $mode = 'out', // out = kurangi, in = kembalikan
        bool $withHpp = false,
    ): float {
        $totalHpp = 0;

        $query = StokBatch::where('barang_id', $barangId)->where('qty_sisa', '>', 0)->lockForUpdate();

        $query = $mode === 'out' ? $query->orderBy('tanggal') : $query->orderByDesc('tanggal');

        foreach ($query->get() as $batch) {
            if ($qty <= 0) {
                break;
            }

            $ambil = min($batch->qty_sisa, $qty);

            $mode === 'out' ? $batch->decrement('qty_sisa', $ambil) : $batch->increment('qty_sisa', $ambil);

            if ($withHpp) {
                $totalHpp += $ambil * $batch->harga;
            }

            $qty -= $ambil;
        }

        return $totalHpp;
    }

    /* =========================
        UPDATE
    ========================== */
    public function update(): void
    {
        $this->validate();

        if ($this->stok < 0) {
            $this->error('Stok tidak mencukupi');
            return;
        }

        DB::transaction(function () {
            $stok = Stok::findOrFail($this->stokModel->id);
            $barang = Barang::findOrFail($stok->barang_id);

            /* =========================
                1️⃣ ROLLBACK TRANSAKSI LAMA
            ========================== */
            $this->fifo($stok->barang_id, $stok->kurang, 'in');
            $this->fifo($stok->barang_id, $stok->kotor, 'in');
            $this->fifo($stok->barang_id, $stok->rusak, 'in');

            /* =========================
                2️⃣ UPDATE LOG STOK
            ========================== */
            $stok->update([
                'barang_id' => $this->barang_id,
                'tanggal' => $this->tanggal,
                'tambah' => $this->tambah,
                'kurang' => $this->kurang,
                'kotor' => $this->kotor,
                'rusak' => $this->pecah,
            ]);

            /* =========================
                3️⃣ APPLY TRANSAKSI BARU
            ========================== */
            $this->fifo($this->barang_id, $this->kurang, 'out');

            /* =========================
                BARANG KADALUARSA
            ========================== */
            if ($this->pecah > 0) {
                $hpp = $this->fifo($this->barang_id, $this->pecah, 'out', true);

                $trx = $this->syncTransaksi('KDL', 'Barang Kadaluarsa', $hpp, $this->pecah);
            }

            /* =========================
                BARANG RETURN
            ========================== */
            if ($this->kotor > 0) {
                $hpp = $this->fifo($this->barang_id, $this->kotor, 'out', true);

                $trx = $this->syncTransaksi('RTN', 'Barang Return', $hpp, $this->kotor);
            }
        });

        $this->success('Transaksi stok berhasil diperbarui', redirectTo: '/stok');
    }

    /* =========================
        SYNC TRANSAKSI HPP
    ========================== */
    private function syncTransaksi(string $kode, string $nama, float $totalHpp, float $qty): void
    {
        $inv = substr($this->stokModel->invoice, -4);
        $tgl = explode('-', $this->stokModel->invoice)[1];

        $trx = Transaksi::firstOrCreate(
            ['invoice' => "INV-$tgl-$kode-$inv"],
            [
                'name' => "$nama " . Barang::find($this->barang_id)->name,
                'user_id' => $this->user_id,
                'tanggal' => $this->tanggal,
                'type' => 'Debit',
                'status' => 'Lunas',
                'bayar' => 'Cash',
            ],
        );

        $trx->update(['total' => $totalHpp]);

        DetailTransaksi::updateOrCreate(
            ['transaksi_id' => $trx->id],
            [
                'barang_id' => $this->barang_id,
                'kategori_id' => Kategori::where('name', $nama)->first()->id,
                'value' => $totalHpp / $qty,
                'kuantitas' => $qty,
                'sub_total' => $totalHpp,
            ],
        );
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Edit Transaksi Stok" separator progress-indicator />

    <x-form wire:submit="update">
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Basic Info" subtitle="Perbarui transaksi stok" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input label="User" :value="auth()->user()->name" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" icon="o-calendar" type="datetime-local"
                            step="1" readonly />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="col-span-2">
                            <x-choices-offline placeholder="Pilih Barang" wire:model.live="barang_id" :options="$barangs"
                                single searchable clearable label="Barang" />
                        </div>
                        <x-input label="Stok Awal" wire:model.live="stokAsli" type="number" step="0.01" readonly />
                        <x-input label="Stok Sekarang" wire:model.live="stok" type="number" step="0.01" readonly />
                    </div>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Detail Items" subtitle="Perbarui detail stok" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end rounded-xl">
                        <x-input label="Stok Bertambah" wire:model.lazy="tambah" type="number" step="0.01"
                            min="0" />
                        <x-input label="Stok Berkurang" wire:model.lazy="kurang" type="number" step="0.01"
                            min="0" />
                        <x-input label="Stok Return" wire:model.lazy="kotor" type="number" step="0.01" />
                        <x-input label="Stok Kadaluarsa" wire:model.lazy="pecah" type="number" step="0.01"
                            min="0" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2 justify-end">
                <x-button spinner label="Cancel" link="/stok" />
                <x-button spinner label="Update" icon="o-check-circle" spinner="update" type="submit"
                    class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-form>
</div>
