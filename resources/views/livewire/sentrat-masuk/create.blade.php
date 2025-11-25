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

new class extends Component {
    use Toast, WithFileUploads;

    #[Rule('required|unique:transaksis,invoice')]
    public string $invoice = '';
    public string $invoice1 = '';

    #[Rule('required')]
    public string $name = '';

    #[Rule('required|numeric|min:1')]
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

    // Semua barang
    public $barangs;

    // Barang yang difilter per detail
    public array $filteredBarangs = [];

    public function with(): array
    {
        return [
            'users' => User::all(),
            'barangs' => $this->barangs,
            'clients' => Client::where('type', 'like', '%Supplier%')
                ->where(function ($q) {
                    $q->where('name', 'like', 'Sentrat%')->orWhere('name', 'like', '%Supriyadi%');
                })
                ->get(),
        ];
    }

    public function mount(): void
    {
        $this->user_id = auth()->id();
        $this->tanggal = now()->format('Y-m-d\TH:i');
        $this->updatedTanggal($this->tanggal);

        $this->barangs = Barang::all();

        $kategori = Kategori::where('name', 'Stok Pakan')->first();
        $this->kategori_id = $kategori->id;
        if ($kategori) {
            $this->kategori_id = $kategori->id;

            // Pastikan minimal ada satu detail
            if (empty($this->details)) {
                $this->details[] = [
                    'barang_id' => null,
                    'value' => 0,
                    'kuantitas' => 1,
                ];
            }

            // Load filteredBarangs per detail
            foreach ($this->details as $index => $detail) {
                $this->filteredBarangs[$index] = Barang::whereHas('jenis', fn($q) => $q->where('kategori_id', $kategori->id))
                    ->get()
                    ->map(
                        fn($barang) => [
                            'id' => $barang->id,
                            'name' => $barang->name,
                        ],
                    )
                    ->toArray();

                // Reset barang_id agar user pilih ulang
                $this->details[$index]['barang_id'] = null;
            }
        }
    }

    public function updatedTanggal($value): void
    {
        if ($value) {
            $tanggal = \Carbon\Carbon::parse($value)->format('Ymd');
            $str = Str::upper(Str::random(4));
            $this->invoice = 'INV-' . $tanggal . '-STR-' . $str;
            $this->invoice1 = 'INV-' . $tanggal . '-UTG-' . $str;
        }
    }

    public function updatedDetails($value, $key): void
    {
        // Hitung total jika value/qty berubah
        if (str_ends_with($key, '.value') || str_ends_with($key, '.kuantitas')) {
            $this->calculateTotal();
        }
    }

    private function calculateTotal(): void
    {
        $this->total = collect($this->details)->sum(fn($item) => ($item['value'] ?? 0) * ($item['kuantitas'] ?? 1));
    }

    public function save(): void
    {
        // ✅ Validasi seluruh input sekaligus
        $this->validate();

        $this->validate([
            'details.*.value' => 'required|numeric|min:0',
            'details.*.barang_id' => 'required|exists:barangs,id',
            'details.*.kuantitas' => 'required|numeric|min:1',
        ]);

        $stok = Transaksi::create([
            'invoice' => $this->invoice,
            'name' => $this->name,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'type' => 'Debit',
            'total' => $this->total,
        ]);

        foreach ($this->details as $item) {
            DetailTransaksi::create([
                'transaksi_id' => $stok->id,
                'kategori_id' => $this->kategori_id,
                'value' => $item['value'], // harga satuan
                'barang_id' => $item['barang_id'] ?? null,
                'kuantitas' => $item['kuantitas'] ?? null,
                'sub_total' => ($item['value'] ?? 0) * ($item['kuantitas'] ?? 1),
            ]);

            // ✅ Tambah stok barang
            if (!empty($item['barang_id']) && !empty($item['kuantitas'])) {
                $barang = Barang::find($item['barang_id']);
                if ($barang) {
                    $barang->increment('stok', $item['kuantitas']);
                }
            }
        }

        $client = Client::find($this->client_id);

        // Normalisasi nama
        $clientName = trim(str_replace(['  '], [' '], $client->name));

        // Tentukan kategori hutang berdasarkan nama client
        if (stripos($clientName, 'SK') !== false) {
            $kategoriName = 'Hutang Sentrat Sk';
        } elseif (stripos($clientName, 'Ponggok') !== false ) {
            $kategoriName = 'Hutang Sentrat Ponggok';
        } elseif (stripos($clientName, 'Bp.Supriyadi') !== false) {
            $kategoriName = 'Saldo Bp.Supriyadi';
        } else {
            $kategoriName = 'Hutang Sentrat Random';
        }

        // Ambil kategori dari database
        $kateHutang = Kategori::where('name', 'like', $kategoriName)->first();

        $hutang = Transaksi::create([
            'invoice' => $this->invoice1,
            'name' => $this->name,
            'user_id' => $this->user_id,
            'tanggal' => $this->tanggal,
            'client_id' => $this->client_id,
            'type' => 'Kredit',
            'total' => $this->total,
        ]);

        foreach ($this->details as $item) {
            DetailTransaksi::create([
                'transaksi_id' => $hutang->id,
                'kategori_id' => $kateHutang->id,
                'value' => $item['value'],
                'barang_id' => $item['barang_id'] ?? null,
                'kuantitas' => $item['kuantitas'] ?? null,
                'sub_total' => ($item['value'] ?? 0) * ($item['kuantitas'] ?? 1),
            ]);
        }

        if ($client) {
            $client->increment('titipan', $this->total);
        }

        // Hitung HPP & stok sekali per barang unik
        $barangIds = collect($this->details)->pluck('barang_id')->unique();

        foreach ($barangIds as $id) {
            $barang = Barang::find($id);
            if (!$barang) {
                continue;
            }

            $stokDebit = DetailTransaksi::where('barang_id', $barang->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('name', 'Stok Pakan'))->sum('kuantitas');

            $totalHarga = DetailTransaksi::where('barang_id', $barang->id)->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('transaksi', fn($q) => $q->where('type', 'Debit'))->whereHas('kategori', fn($q) => $q->where('name', 'Stok Pakan'))->sum(\DB::raw('value * kuantitas'));

            $hppBaru = $stokDebit > 0 ? $totalHarga / $stokDebit : 0;

            $barang->update([
                'hpp' => $hppBaru,
            ]);
        }

        $this->success('Transaksi berhasil dibuat!', redirectTo: '/sentrat-masuk');
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'value' => 0,
            'barang_id' => null,
            'kuantitas' => 1,
        ];

        $index = count($this->details) - 1;

        // Jika kategori sudah dipilih, filter barang sesuai kategori
        if ($this->kategori_id) {
            $kategori = Kategori::find($this->kategori_id);
            $this->filteredBarangs[$index] = Barang::whereHas('jenis', fn($q) => $q->where('kategori_id', $kategori->id))
                ->get()
                ->map(
                    fn($barang) => [
                        'id' => $barang->id,
                        'name' => $barang->name,
                    ],
                )
                ->toArray();
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
};
?>

<div class="p-4 space-y-6">
    <x-header title="Create Transaksi Pembelian Pakan" separator progress-indicator />

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
                        <x-input label="Rincian" wire:model="name" placeholder="Contoh: Pembelian Sentrat" />
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
                <x-button spinner label="Create" icon="o-paper-airplane" spinner="save" type="submit"
                    class="btn-primary" />
            </div>
        </x-slot:actions>
    </x-form>
</div>
