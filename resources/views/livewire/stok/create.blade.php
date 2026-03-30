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
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required|unique:transaksis,invoice')]
    public string $invoice = '';
    public string $invoice1 = '';
    public string $invoice2 = '';

    #[Rule('required')]
    public ?int $barang_id = null;

    #[Rule('required')]
    public ?string $tanggal = null;

    public ?int $user_id = null;
    public float $stok = 0;
    public float $awal = 0;

    #[Rule('nullable|numeric|min:0')]
    public float $tambah = 0;

    #[Rule('nullable|numeric|min:0')]
    public float $kurang = 0;

    public function with(): array
    {
        return [
            'users' => User::all(),
            'barangs' => Barang::all(),
        ];
    }

    public function mount(): void
    {
        $this->user_id = auth()->id();
        $this->tanggal = now()->format('Y-m-d\TH:i:s');
        $this->updatedTanggal($this->tanggal);
    }

    public function updatedTanggal($value): void
    {
        if ($value) {
            $tanggal = Carbon::parse($value)->format('Ymd');
            $str = Str::upper(Str::random(4));
            $this->invoice = 'INV-' . $tanggal . '-UPD-' . $str;
            $this->invoice1 = 'INV-' . $tanggal . '-TBH-' . $str;
            $this->invoice2 = 'INV-' . $tanggal . '-KRG-' . $str;
        }
    }

    public function updatedBarangId($id): void
    {
        if ($id) {
            $barang = StokBatch::where('barang_id', $id)->sum('qty_sisa');
            $this->stok = $barang ?? 0;
            $this->awal = $barang ?? 0;
        }
    }

    public function updated($field): void
    {
        if (in_array($field, ['tambah', 'kurang'])) {
            $barang = StokBatch::where('barang_id', $this->barang_id)->sum('qty_sisa');
            if ($barang) {
                $stok_awal = $barang ?? 0;
                $stok_baru = $stok_awal + $this->tambah - $this->kurang;
                $this->stok = max(0, $stok_baru);
            }
        }
    }

    private function tambahStokFifoDanHitungHpp(int $barangId, float $qtyKeluar): float
    {
        $totalHpp = 0;

        $batches = StokBatch::where('barang_id', $barangId)->where('qty_sisa', '>', 0)->orderByDesc('tanggal')->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if ($qtyKeluar <= 0) {
                break;
            }

            $ambil = min($batch->qty_sisa, $qtyKeluar);

            $batch->increment('qty_sisa', $ambil);

            $totalHpp += $ambil * $batch->harga;

            $qtyKeluar -= $ambil;
        }

        return $totalHpp;
    }

    private function kurangiStokFifoDanHitungHpp(int $barangId, float $qtyKeluar): float
    {
        $totalHpp = 0;

        $batches = StokBatch::where('barang_id', $barangId)->where('qty_sisa', '>', 0)->orderBy('tanggal')->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if ($qtyKeluar <= 0) {
                break;
            }

            $ambil = min($batch->qty_sisa, $qtyKeluar);

            $batch->decrement('qty_sisa', $ambil);

            $totalHpp += $ambil * $batch->harga;

            $qtyKeluar -= $ambil;
        }

        return $totalHpp;
    }

    public function save(): void
    {
        $this->validate();
        if ($this->stok <= 0) {
            $this->error('Stok tidak mencukupi untuk pengurangan atau transaksi gagal');
            return;
        }

        DB::transaction(function () {
            /* =========================
                LOG STOK
            ========================== */
            Stok::create([
                'invoice' => $this->invoice,
                'user_id' => $this->user_id,
                'barang_id' => $this->barang_id,
                'tanggal' => $this->tanggal,
                'tambah' => $this->tambah,
                'kurang' => $this->kurang,
            ]);

            $kategori = Kategori::where('name', 'Perbaikan Stok')->first();

            if ($this->tambah > 0) {
                $hppReturn = $this->tambahStokFifoDanHitungHpp($this->barang_id, $this->tambah);

                $transaksi = Transaksi::create([
                    'invoice' => $this->invoice1,
                    'name' => 'Barang Tambah ' . Barang::find($this->barang_id)->name,
                    'user_id' => $this->user_id,
                    'tanggal' => $this->tanggal,
                    'type' => 'Debit',
                    'total' => $hppReturn * $this->tambah,
                    'status' => 'Lunas',
                    'bayar' => 'Cash',
                ]);

                $detail = DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $this->barang_id,
                    'kategori_id' => $kategori->id,
                    'value' => $hppReturn,
                    'kuantitas' => $this->tambah,
                    'sub_total' => $hppReturn * $this->tambah,
                ]);
            }

            if ($this->kurang > 0) {
                $hppReturn = $this->kurangiStokFifoDanHitungHpp($this->barang_id, $this->kurang);

                $transaksi = Transaksi::create([
                    'invoice' => $this->invoice2,
                    'name' => 'Barang Kurang ' . Barang::find($this->barang_id)->name,
                    'user_id' => $this->user_id,
                    'tanggal' => $this->tanggal,
                    'type' => 'Kredit',
                    'total' => $hppReturn,
                    'status' => 'Lunas',
                    'bayar' => 'Cash',
                ]);

                $kategori = Kategori::where('name', 'Perbaikan Stok')->first();

                $detail = DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $this->barang_id,
                    'kategori_id' => $kategori->id,
                    'value' => $hppReturn / $this->kurang,
                    'kuantitas' => $this->kurang,
                    'sub_total' => $hppReturn,
                ]);
            }
        });

        $this->success('Stok & transaksi berhasil diperbarui', redirectTo: '/stok');
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Create Transaksi Stok Pakan" separator progress-indicator />

    <x-form wire:submit="save">
        <!-- SECTION: Basic Info -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Basic Info" subtitle="Buat transaksi baru" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input label="User" :value="auth()->user()->name" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" type="datetime-local" step="1" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="col-span-2">
                            <x-choices-offline placeholder="Pilih Barang" wire:model.live="barang_id" :options="$barangs"
                                single searchable clearable label="Barang" />
                        </div>
                        <x-input label="Stok Awal" wire:model.live="awal" type="number" step="0.01" readonly />
                        <x-input label="Stok Sekarang" wire:model.live="stok" type="number" step="0.01" readonly />
                    </div>
                </div>
            </div>
        </x-card>

        <!-- SECTION: Detail Items -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Detail Items" subtitle="Tambah detail transaksi" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end p-3 rounded-xl">
                        <x-input label="Barang Bertambah" wire:model.lazy="tambah" type="number" step="0.01"
                            min="0" />
                        <x-input label="Barang Berkurang" wire:model.lazy="kurang" type="number" step="0.01"
                            min="0" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2 justify-end">
                <x-button spinner label="Cancel" link="/stok" />
                <x-button spinner label="Create" icon="o-paper-airplane" spinner="save" type="submit"
                    class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-form>
</div>
