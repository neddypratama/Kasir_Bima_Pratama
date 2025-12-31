    <?php
    
    use Livewire\Volt\Component;
    use App\Models\Transaksi;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\DB;
    
    new class extends Component {
        public $startDate;
        public $endDate;
    
        public $pendapatanData = [];
        public $pengeluaranData = [];
        public $expanded = [];
    
        /* ======================
            MOUNT
        ====================== */
        public function mount()
        {
            $this->generateReport();
        }
    
        public function updated($field)
        {
            if (in_array($field, ['startDate', 'endDate'])) {
                $this->generateReport();
            }
        }
    
        /* ======================
            GENERATE REPORT
        ====================== */
        public function generateReport()
        {
            $first = Transaksi::orderBy('tanggal')->first();
            $last = Transaksi::orderByDesc('tanggal')->first();
    
            if (!$first || !$last) {
                return;
            }
    
            $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::parse($first->tanggal)->startOfDay();
    
            $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::parse($last->tanggal)->endOfDay();
    
            /* ======================
                1. AMBIL LAPORAN + KATEGORI
            ====================== */
            $laporans = DB::table('laporans')->leftJoin('kategoris', 'kategoris.laporan_id', '=', 'laporans.id')->select('laporans.id as laporan_id', 'laporans.name as laporan', 'laporans.type', 'kategoris.id as kategori_id', 'kategoris.name as kategori')->orderBy('laporans.id')->orderBy('kategoris.name')->get();
    
            /* ======================
                2. INIT DATA = 0
            ====================== */
            $this->pendapatanData = [];
            $this->pengeluaranData = [];
    
            foreach ($laporans as $row) {
                if ($row->type === 'Pendapatan') {
                    $this->pendapatanData[$row->laporan]['detail'][$row->kategori] = 0;
                    $this->pendapatanData[$row->laporan]['total'] ??= 0;
                }
    
                if ($row->type === 'Pengeluaran') {
                    $this->pengeluaranData[$row->laporan]['detail'][$row->kategori] = 0;
                    $this->pengeluaranData[$row->laporan]['total'] ??= 0;
                }
            }
    
            /* ======================
                3. AMBIL TRANSAKSI
            ====================== */
            $rows = DB::table('detail_transaksis as dt')
                ->join('kategoris as k', 'k.id', '=', 'dt.kategori_id')
                ->join('laporans as l', 'l.id', '=', 'k.laporan_id')
                ->join('transaksis as t', 't.id', '=', 'dt.transaksi_id')
                ->select('l.name as laporan', 'l.type', 'k.name as kategori', DB::raw('SUM(dt.sub_total) as total'))
                ->where('t.status', 'Lunas')
                ->whereBetween('t.tanggal', [$start, $end])
                ->groupBy('l.name', 'l.type', 'k.name')
                ->get();
    
    
            /* ======================
                4. ISI NILAI
            ====================== */
            foreach ($rows as $row) {
                if ($row->type === 'Pendapatan') {
                    $this->pendapatanData[$row->laporan]['detail'][$row->kategori] += $row->total;
                    $this->pendapatanData[$row->laporan]['total'] += $row->total;
                }
    
                if ($row->type === 'Pengeluaran') {
                    $this->pengeluaranData[$row->laporan]['detail'][$row->kategori] += $row->total;
                    $this->pengeluaranData[$row->laporan]['total'] += $row->total;
                }
            }
    
            // toggle default
            foreach (array_keys($this->pendapatanData) as $k) {
                $this->expanded[$k] = false;
            }
            foreach (array_keys($this->pengeluaranData) as $k) {
                $this->expanded[$k] = false;
            }
        }
    
        public function with(): array
        {
            $totalPendapatan = array_sum(array_column($this->pendapatanData, 'total'));
            $totalPengeluaran = array_sum(array_column($this->pengeluaranData, 'total'));
    
            return [
                'pendapatanData' => $this->pendapatanData,
                'pengeluaranData' => $this->pengeluaranData,
                'totalPendapatan' => $totalPendapatan,
                'totalPengeluaran' => $totalPengeluaran,
                'totalLaba' => $totalPendapatan - $totalPengeluaran,
            ];
        }
    };
    ?>

    <div class="p-6 space-y-6">
        <x-header title="Laporan Laba Rugi" separator>
            <x-slot:actions>
                <x-button wire:click="export" icon="fas.download" primary>Export Excel</x-button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 items-end">
                    <x-input type="date" label="Dari Tanggal" wire:model.live="startDate" />
                    <x-input type="date" label="Sampai Tanggal" wire:model.live="endDate" />
                </div>
            </x-slot:actions>
        </x-header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-card>
                <h3 class="text-lg font-semibold text-green-800">
                    <i class="fas fa-coins text-green-600"></i> Total Pendapatan
                </h3>
                <p class="text-2xl font-bold text-green-700 mt-2">
                    Rp {{ number_format($totalPendapatan, 0, ',', '.') }}
                </p>
            </x-card>

            <x-card>
                <h3 class="text-lg font-semibold text-red-800">
                    <i class="fas fa-wallet text-red-600"></i> Total Pengeluaran
                </h3>
                <p class="text-2xl font-bold text-red-700 mt-2">
                    Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}
                </p>
            </x-card>
            @if ($totalLaba > 0)
                <x-card>
                    <h3 class="text-lg font-semibold text-green-800">
                        <i class="fas fa-wallet text-green-600"></i> Total
                    </h3>
                    <p class="text-2xl font-bold text-green-700 mt-2">
                        Rp {{ number_format($totalLaba, 0, ',', '.') }}
                    </p>
                </x-card>
            @else
                <x-card>
                    <h3 class="text-lg font-semibold text-red-800">
                        <i class="fas fa-wallet text-red-600"></i> Total Laba
                    </h3>
                    <p class="text-2xl font-bold text-red-700 mt-2">
                        Rp {{ number_format($totalLaba, 0, ',', '.') }}
                    </p>
                </x-card>
            @endif
        </div>

        <x-card class="mt-4">
            <h3 class="text-xl font-semibold mb-4"><i class="fas fa-list-ul"></i>Rincian</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-lg font-semibold text-green-700 mb-2"><i class="fas fa-arrow-up"></i>Pendapatan per
                        Kelompok</h4>
                    <ul class="divide-y divide-gray-200">
                        @foreach ($pendapatanData as $kelompok => $data)
                            <li class="py-2">
                                <div class="flex justify-between cursor-pointer"
                                    wire:click="$toggle('expanded.{{ $kelompok }}')">
                                    <span class="font-medium">{{ $kelompok }}</span>
                                    <span class="text-green-700">Rp
                                        {{ number_format($data['total'], 0, ',', '.') }}</span>
                                </div>
                                @if ($expanded[$kelompok] ?? false)
                                    <ul class="pl-4 mt-2">
                                        @foreach ($data['detail'] as $sub => $val)
                                            <li class="flex justify-between py-1 text-green-600">
                                                <span>{{ $sub }}</span>
                                                <span>Rp {{ number_format($val, 0, ',', '.') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 class="text-lg font-semibold text-red-700 mb-2"><i class="fas fa-arrow-down"></i>Pengeluaran per
                        Kelompok</h4>
                    <ul class="divide-y divide-gray-200">
                        @foreach ($pengeluaranData as $kelompok => $data)
                            <li class="py-2">
                                <div class="flex justify-between cursor-pointer"
                                    wire:click="$toggle('expanded.{{ $kelompok }}')">
                                    <span class="font-medium">{{ $kelompok }}</span>
                                    <span class="text-red-700">Rp
                                        {{ number_format($data['total'], 0, ',', '.') }}</span>
                                </div>
                                @if ($expanded[$kelompok] ?? false)
                                    <ul class="pl-4 mt-2">
                                        @foreach ($data['detail'] as $sub => $val)
                                            <li class="flex justify-between py-1 text-red-600">
                                                <span>{{ $sub }}</span>
                                                <span>Rp {{ number_format($val, 0, ',', '.') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </x-card>
    </div>
