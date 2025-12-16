<?php

use Livewire\Volt\Component;
use App\Models\KonversiSatuan;
use App\Models\Barang;
use App\Models\JenisBarang;
use Mary\Traits\Toast;
use Livewire\Attributes\Rule;

new class extends Component {
    use Toast;

    public Barang $barang;

    #[Rule('required|string')]
    public string $name = '';

    #[Rule('required|exists:jenis_barangs,id')]
    public ?int $jenis_id = null;

    #[Rule('required|numeric|min:0')]
    public float $stok = 0;

    #[Rule('required|string')]
    public string $satuan = '';

    #[Rule('nullable|numeric|min:0')]
    public float $hpp = 0;

    #[Rule('required|array|min:1')]
    public array $details = [];

    public function mount(Barang $barang): void
    {
        $this->barang = $barang;

        $this->name = $barang->name;
        $this->jenis_id = $barang->jenis_id;
        $this->stok = $barang->stok;
        $this->satuan = $barang->satuan;
        $this->hpp = $barang->hpp ?? 0;

        $this->details = $barang->satuans
            ->map(
                fn($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'konversi' => $s->konversi,
                    'harga' => $s->harga,
                ],
            )
            ->toArray();

        if (count($this->details) === 0) {
            $this->addDetail();
        }
    }

    public function with(): array
    {
        return [
            'jenisbarang' => JenisBarang::all(),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|unique:barangs,name,' . $this->barang->id,
            'details.*.name' => 'required|string|min:1',
            'details.*.konversi' => 'required|numeric|min:1',
            'details.*.harga' => 'required|numeric|min:1',
        ]);

        // pastikan satuan dasar ada
        if (!collect($this->details)->contains(fn($d) => strtolower($d['name']) === strtolower($this->satuan))) {
            $this->error("Satuan dasar ({$this->satuan}) wajib ada");
            return;
        }

        $this->barang->update([
            'name' => $this->name,
            'jenis_id' => $this->jenis_id,
            'stok' => $this->stok,
            'satuan' => $this->satuan,
            'hpp' => $this->hpp,
        ]);

        $ids = [];

        foreach ($this->details as $item) {
            $satuan = KonversiSatuan::updateOrCreate(
                [
                    'id' => $item['id'] ?? null,
                    'barang_id' => $this->barang->id,
                ],
                [
                    'name' => $item['name'],
                    'konversi' => $item['konversi'],
                    'harga' => $item['harga'],
                ],
            );

            $ids[] = $satuan->id;
        }

        // hapus satuan yang dihapus di form
        KonversiSatuan::where('barang_id', $this->barang->id)->whereNotIn('id', $ids)->delete();

        $this->success('Barang berhasil diperbarui', redirectTo: '/barangs');
    }

    public function addDetail(): void
    {
        $this->details[] = [
            'id' => null,
            'name' => '',
            'konversi' => 1,
            'harga' => 0,
        ];
    }

    public function removeDetail(int $index): void
    {
        if (count($this->details) <= 1) {
            $this->warning('Minimal harus ada 1 satuan');
            return;
        }

        unset($this->details[$index]);
        $this->details = array_values($this->details);
    }
};
?>

<div>
    <x-header title="Update {{$this->name}}" separator />

    <x-form wire:submit="save">

        {{-- ===================== BASIC SECTION ===================== --}}
        <div class="lg:grid grid-cols-8 gap-5">
            <div class="col-span-3">
                <x-header title="Basic" subtitle="Informasi dasar barang" size="text-2xl" />
            </div>

            <div class="col-span-5 grid gap-3">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <x-input label="Nama Barang" wire:model="name" placeholder="Contoh: Dog Food Premium" />
                    </div>
                    <div class="col-span-2">
                        <x-select label="Jenis Barang" wire:model="jenis_id" :options="$jenisbarang" option-label="name"
                            option-value="id" placeholder="Pilih jenis barang" />
                    </div>
                    <div class="col-span-2">
                        <x-input label="Harga Pokok (HPP)" wire:model="hpp" prefix="Rp " money="IDR" />
                    </div>
                    <x-input label="Stok" wire:model="stok" type="number" min="0" step="0.01" />
                    <x-input label="Satuan Dasar" wire:model.live="satuan" placeholder="Contoh: Kg" />
                </div>
            </div>
        </div>

        <hr class="my-5" />

        {{-- ===================== DETAILS SECTION ===================== --}}
        <div class="lg:grid grid-cols-8 gap-5">
            <div class="col-span-3">
                <x-header title="Details" subtitle="Informasi lengkap barang" size="text-2xl" />
            </div>
            <div class="col-span-5 grid gap-3">
                @foreach ($details as $index => $item)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <x-input label="Satuan Jual" wire:model.live="details.{{ $index }}.name"
                            placeholder="Contoh: Kg" />
                        <x-input label="1 {{ $item['name'] }} = ? {{ $this->satuan }}" type="number" min="1"
                            wire:model.lazy="details.{{ $index }}.konversi" />
                        <x-input label="Harga Jual" wire:model="details.{{ $index }}.harga" prefix="Rp "
                            money="IDR" />
                    </div>
                    <div class="flex justify-end">
                        <x-button wire:click="removeDetail({{ $index }})" icon="o-trash" label="Hapus"
                            class="btn-error btn-sm" />
                    </div>
                @endforeach
                <div class="flex justify-start mt-2">
                    <x-button icon="o-plus" class="btn-primary" wire:click="addDetail" label="Tambah Item" />
                </div>
            </div>
        </div>

        {{-- ===================== ACTION BUTTON ===================== --}}
        <x-slot:actions>
            <x-button label="Cancel" link="/barangs" />
            <x-button label="Save" icon="o-paper-airplane" spinner="save" type="submit" class="btn-primary" />
        </x-slot:actions>

    </x-form>
</div>