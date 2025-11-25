<?php

use Livewire\Volt\Component;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Client;
use App\Models\User;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;

new class extends Component {
    use Toast, WithFileUploads;

    public Transaksi $transaksi;

    #[Rule('required')]
    public string $invoice = '';

    #[Rule('required')]
    public string $name = '';

    #[Rule('required|numeric|min:0')]
    public float $total = 0;

    #[Rule('required')]
    public ?int $user_id = null;

    #[Rule('required')]
    public ?int $client_id = null;

    #[Rule('required')]
    public ?int $kategori_id = null;

    public ?string $tanggal = null;

    #[Rule('required|array|min:1')]
    public array $details = [];

    public $barangs;
    public $pokok;
    public array $filteredBarangs = [];

    public function mount(Transaksi $transaksi): void
    {
        $this->transaksi = $transaksi->load('details');
        $this->invoice = $transaksi->invoice;
        $this->name = $transaksi->name;
        $this->user_id = $transaksi->user_id;
        $this->client_id = $transaksi->client_id;
        $this->kategori_id = $transaksi->details->first()?->kategori_id;
        $this->tanggal = Carbon::parse($transaksi->tanggal)->format('Y-m-d\TH:i:s');
        $this->total = $transaksi->total;

        $this->barangs = Barang::all();
        $this->details = $transaksi->details
            ->map(
                fn($d) => [
                    'barang_id' => $d->barang_id,
                    'value' => $d->value,
                    'kuantitas' => $d->kuantitas,
                ],
            )
            ->toArray();

        // Set filteredBarangs per detail
        foreach ($this->details as $index => $detail) {
            $this->filteredBarangs[$index] = Barang::whereHas('jenis', fn($q) => $q->where('kategori_id', $this->kategori_id))
                ->get()
                ->map(
                    fn($barang) => [
                        'id' => $barang->id,
                        'name' => $barang->name,
                    ],
                )
                ->toArray();
        }
    }

    public function with(): array
    {
        return [
            'users' => User::all(),
            'barangs' => $this->barangs,
            'kategoris' => Kategori::where('name', 'like', '%Tray%')
                ->where(function ($q) {
                    $q->where('type', 'like', '%Pendapatan%')->orWhere('type', 'like', '%Aset%');
                })
                ->get(),
            'clients' => Client::where('type', 'like', '%Supplier%')
                ->where(function ($q) {
                    $q->where('name', 'like', 'Sentrat%')->orWhere('name', 'like', '%Supriyadi%');
                })
                ->get(),
        ];
    }

    public function updatedDetails($value, $key): void
    {
        if (str_ends_with($key, '.value') || str_ends_with($key, '.kuantitas')) {
            $this->calculateTotal();
        }
    }

    public function updatedKategoriId($value): void
    {
        foreach ($this->details as $index => $detail) {
            $this->filteredBarangs[$index] = Barang::whereHas('jenis', fn($q) => $q->where('kategori_id', $value))->get()->map(fn($barang) => ['id' => $barang->id, 'name' => $barang->name])->toArray();

            $this->details[$index]['barang_id'] = null;
        }
    }

    private function calculateTotal(): void
    {
        $this->total = collect($this->details)->sum(fn($item) => ($item['value'] ?? 0) * ($item['kuantitas'] ?? 1));
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'value' => 0,
            'barang_id' => null,
            'kuantitas' => 1,
        ];

        $index = count($this->details) - 1;
        if ($this->kategori_id) {
            $this->filteredBarangs[$index] = Barang::whereHas('jenis', fn($q) => $q->where('kategori_id', $this->kategori_id))->get()->map(fn($barang) => ['id' => $barang->id, 'name' => $barang->name])->toArray();
        } else {
            $this->filteredBarangs[$index] = [];
        }

        $this->calculateTotal();
    }

    public function removeDetail(int $index): void
    {
        unset($this->details[$index]);
        unset($this->filteredBarangs[$index]);
        $this->details = array_values($this->details);
        $this->filteredBarangs = array_values($this->filteredBarangs);
        $this->calculateTotal();
    }

    public function save(): void
    {
        $this->validate([
            'details.*.barang_id' => 'required|exists:barangs,id',
            'details.*.value' => 'required|numeric|min:0',
            'details.*.kuantitas' => 'required|numeric|min:1',
        ]);

        // 1️⃣ Rollback stok lama (hitung ulang stok & HPP tanpa transaksi ini)
        foreach ($this->transaksi->details as $oldDetail) {
            $barang = Barang::find($oldDetail->barang_id);
            if (!$barang) {
                continue;
            }

            // Hitung ulang stok dari semua transaksi lain (tanpa transaksi ini)
            $stokBaru = Barang::find($oldDetail->barang_id)->stok - $oldDetail->kuantitas;

            // Hitung ulang HPP dari semua transaksi pembelian lain
            $totalHarga = DetailTransaksi::where('barang_id', $barang->id)->where('transaksi_id', '!=', $this->transaksi->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('name', 'Stok Pakan'))->sum(\DB::raw('value * kuantitas'));

            $totalQty = DetailTransaksi::where('barang_id', $barang->id)->where('transaksi_id', '!=', $this->transaksi->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('name', 'Stok Pakan'))->sum('kuantitas');

            $hppBaru = $totalQty > 0 ? $totalHarga / $totalQty : 0;

            $barang->update([
                'stok' => $stokBaru,
                'hpp' => $hppBaru,
            ]);
        }

        $suffix = substr($this->transaksi->invoice, -4);
        $part = explode('-', $this->transaksi->invoice);
        $tanggal = $part[1];
        $hutang = Transaksi::where('invoice', 'like', "%$tanggal-UTG-$suffix")->first();
        $client = Client::find($this->client_id);

        // Normalisasi nama
        $clientName = trim(str_replace(['  '], [' '], $client->name));

        // Tentukan kategori hutang berdasarkan nama client
        if (stripos($clientName, 'SK') !== false) {
            $kategoriName = 'Hutang Sentrat Sk';
        } elseif (stripos($clientName, 'Ponggok') !== false) {
            $kategoriName = 'Hutang Sentrat Ponggok';
        } elseif (stripos($clientName, 'Bp.Supriyadi') !== false) {
            $kategoriName = 'Saldo Bp.Supriyadi';
        } else {
            $kategoriName = 'Hutang Sentrat Random';
        }

        // Ambil kategori dari database
        $kateHutang = Kategori::where('name', 'like', $kategoriName)->first();

        $hutang->update([
            'name' => $this->name,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'tanggal' => $this->tanggal,
            'total' => $this->total,
            'type' => 'Kredit',
        ]);
        $hutang->details()->delete();
        foreach ($this->details as $item) {
            DetailTransaksi::create([
                'transaksi_id' => $hutang->id,
                'kategori_id' => $kateHutang->id,
                'barang_id' => $item['barang_id'],
                'value' => $item['value'],
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $item['value'] * $item['kuantitas'],
            ]);
        }

        $oldClient = Client::find($this->transaksi->getOriginal('client_id'));
        $newClient = Client::find($this->client_id);

        // Jika client lama dan baru berbeda
        if ($oldClient && $newClient && $oldClient->id !== $newClient->id) {
            // Kembalikan titipan client lama
            $oldClient->decrement('titipan', $this->transaksi->total);

            // Tambahkan titipan ke client baru
            $newClient->increment('titipan', $this->total);
        } elseif ($newClient) {
            // Jika client sama, hanya update selisih total
            $selisih = $this->total - $this->transaksi->total;

            if ($selisih > 0) {
                $newClient->increment('titipan', $selisih);
            } elseif ($selisih < 0) {
                $newClient->decrement('titipan', abs($selisih));
            }
        }

        // 2️⃣ Update transaksi utama
        $this->transaksi->update([
            'name' => $this->name,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'kategori_id' => $this->kategori_id,
            'tanggal' => $this->tanggal,
            'total' => $this->total,
            'type' => 'Debit',
        ]);

        // 3️⃣ Hapus detail lama
        $this->transaksi->details()->delete();

        // 4️⃣ Simpan detail baru
        foreach ($this->details as $item) {
            DetailTransaksi::create([
                'transaksi_id' => $this->transaksi->id,
                'kategori_id' => $this->kategori_id,
                'barang_id' => $item['barang_id'],
                'value' => $item['value'],
                'kuantitas' => $item['kuantitas'],
                'sub_total' => $item['value'] * $item['kuantitas'],
            ]);

            // ✅ Tambah stok barang
            if (!empty($item['barang_id']) && !empty($item['kuantitas'])) {
                $barang = Barang::find($item['barang_id']);
                if ($barang) {
                    $barang->increment('stok', $item['kuantitas']);
                }
            }
        }

        // 5️⃣ Setelah semua detail baru disimpan, update stok & HPP berdasarkan semua transaksi aktif
        $barangIds = collect($this->details)->pluck('barang_id')->unique();

        foreach ($barangIds as $barangId) {
            $barang = Barang::find($barangId);
            if (!$barang) {
                continue;
            }

            $stokDebit = DetailTransaksi::where('barang_id', $barang->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('name', 'Stok Pakan'))->sum('kuantitas');
            $totalHarga = DetailTransaksi::where('barang_id', $barang->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('name', 'Stok Pakan'))->sum(\DB::raw('value * kuantitas'));
            $totalQty = $stokDebit;
            $stokBaru = $barang->stok;

            $hppBaru = $totalQty > 0 ? $totalHarga / $totalQty : 0;

            // Jika stok <= 0, reset HPP ke 0
            if ($stokBaru <= 0) {
                $hppBaru = 0;
            }

            $barang->update([
                'hpp' => $hppBaru,
            ]);
        }

        $this->success('Transaksi berhasil diupdate!', redirectTo: '/sentrat-masuk');
    }
};
?>

<div class="p-4 space-y-6">
    <x-header title="Update Transaksi {{ $this->invoice }}" separator progress-indicator />

    <x-form wire:submit="save">
        <!-- SECTION: Basic Info -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Basic Info" subtitle="Buat transaksi baru" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input label="Invoice" wire:model="invoice" readonly />
                        <x-input label="User" :value="auth()->user()->name" readonly />
                        <x-datetime label="Date + Time" wire:model="tanggal" icon="o-calendar" type="datetime-local" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input label="Rincian" wire:model="name" />
                        <x-choices-offline placeholder="Pilih Client" wire:model.live="client_id" :options="$clients"
                            single searchable clearable label="Client">
                            {{-- Tampilan item di dropdown --}} @scope('item', $clients)
                                <x-list-item :item="$clients" sub-value="invoice">
                                    <x-slot:actions>
                                        <x-badge :value="$clients->type ?? 'Tanpa Client'" class="badge-soft badge-secondary badge-sm" />

                                    </x-slot:actions>
                                </x-list-item>
                            @endscope

                            {{-- Tampilan ketika sudah dipilih --}}
                            @scope('selection', $clients)
                                {{ $clients->name . ' | ' . $clients->type }}
                            @endscope
                        </x-choices-offline>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- SECTION: Detail Items -->
        <x-card>
            <div class="lg:grid grid-cols-8 gap-4">
                <div class="col-span-2">
                    <x-header title="Detail Items" subtitle="Tambah barang ke transaksi" size="text-2xl" />
                </div>
                <div class="col-span-6 grid gap-3">
                    @foreach ($details as $index => $item)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end p-3 rounded-xl">
                            <x-choices-offline wire:model.live="details.{{ $index }}.barang_id" label="Barang"
                                :options="$filteredBarangs[$index] ?? []" placeholder="Pilih Barang" searchable single clearable />
                            <x-input label="Harga Satuan" wire:model.live="details.{{ $index }}.value"
                                prefix="Rp " money="IDR" />
                            <x-input label="Qty" wire:model.lazy="details.{{ $index }}.kuantitas"
                                type="number" min="1" step="0.01" />
                            <x-input label="Total" :value="number_format(($item['value'] ?? 0) * ($item['kuantitas'] ?? 0), 0, '.', ',')" prefix="Rp" readonly />
                        </div>
                        <div class="flex justify-end">
                            <x-button spinner icon="o-trash" wire:click="removeDetail({{ $index }})"
                                class="btn-error btn-sm" label="Hapus Item" />
                        </div>
                    @endforeach

                    <div class="flex flex-wrap gap-3 justify-between items-center border-t pt-4">
                        <x-button spinner icon="o-plus" label="Tambah Item" wire:click="addDetail"
                            class="btn-primary" />
                        <x-input label="Total Pembelian" :value="'Rp ' . number_format($total, 0, ',', '.')" readonly class="max-w-xs" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-slot:actions>
            <div class="flex flex-row sm:flex-row gap-2 justify-end">
                <x-button spinner label="Cancel" link="/sentrat-masuk" />
                <x-button spinner label="Update" icon="o-check" spinner="save" type="submit" class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-form>
</div>
