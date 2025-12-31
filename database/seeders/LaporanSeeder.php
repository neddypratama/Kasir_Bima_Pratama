<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaporanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // --- KELOMPOK PENDAPATAN / PENJUALAN ---
            [
                'name' => 'Penjualan Pakan',
                'deskripsi' => 'Pendapatan dari penjualan pakan.',
                'type' => 'Pendapatan',
            ],
            [
                'name' => 'Penjualan Obat-Obatan',
                'deskripsi' => 'Pendapatan dari penjualan obat-obatan.',
                'type' => 'Pendapatan',
            ],
            [
                'name' => 'Penjualan Barang',
                'deskripsi' => 'Pendapatan dari penjualan barang-barang.',
                'type' => 'Pendapatan',
            ],
            // --- KELOMPOK HPP (HARGA POKOK PENJUALAN) ---
            [
                'name' => 'HPP Pakan',
                'deskripsi' => 'Harga pokok modal untuk produk pakan.',
                'type' => 'Pengeluaran',
            ],
            [
                'name' => 'HPP Obat',
                'deskripsi' => 'Harga pokok modal untuk produk obat-obatan.',
                'type' => 'Pengeluaran',
            ],
            [
                'name' => 'HPP Barang',
                'deskripsi' => 'Harga pokok modal untuk produk barang-barang.',
                'type' => 'Pengeluaran',
            ],

            // --- KELOMPOK BEBAN OPERASIONAL ---
            [
                'name' => 'Beban Usaha',
                'deskripsi' => 'Pengeluaran yang terjadi dalam proses operasional bisnis.',
                'type' => 'Pengeluaran',
            ],

            // --- KELOMPOK BON ---
            [
                'name' => 'Stok Pakan',
                'deskripsi' => 'Piutang atau tagihan dari pakan.',
                'type' => 'Aset',
            ],
            [
                'name' => 'Stok Obat-Obatan',
                'deskripsi' => 'Piutang atau tagihan dari obat-obatan.',
                'type' => 'Aset',
            ],
            [
                'name' => 'Stok Barang',
                'deskripsi' => 'Piutang atau tagihan dari barang-barang.',
                'type' => 'Aset',
            ],
        ];

        DB::table('laporans')->insert($categories);
    }
}
