<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriSeeder extends Seeder
{
    public function run()
    {
        
        $categories = [
            // --- KELOMPOK PENDAPATAN / PENJUALAN ---
            [
                'name' => 'Penjualan Hijauan',
                'deskripsi' => 'Pendapatan dari penjualan pakan jenis hijauan.',
                'laporan_id' => 1,
            ],
            [
                'name' => 'Penjualan Konsentrat',
                'deskripsi' => 'Pendapatan dari penjualan pakan konsentrat.',
                'laporan_id' => 1,
            ],
            [
                'name' => 'Penjualan Bahan Baku Konsentrat',
                'deskripsi' => 'Pendapatan dari penjualan bahan baku mentah konsentrat.',
                'laporan_id' => 1,
            ],
            [
                'name' => 'Penjualan Premix',
                'deskripsi' => 'Pendapatan dari penjualan suplemen/premix.',
                'laporan_id' => 1,
            ],
            [
                'name' => 'Penjualan Pakan Kucing',
                'deskripsi' => 'Pendapatan dari penjualan kategori pakan kucing.',
                'laporan_id' => 1,
            ],
            [
                'name' => 'Penjualan Obat-Obatan RMN',
                'deskripsi' => 'Pendapatan dari penjualan obat ruminansia.',
                'laporan_id' => 2,
            ],
            [
                'name' => 'Penjualan Obat-Obatan Unggas',
                'deskripsi' => 'Pendapatan dari penjualan obat unggas.',
                'laporan_id' => 2,
            ],
            [
                'name' => 'Penjualan Barang',
                'deskripsi' => 'Pendapatan dari penjualan barang-barang.',
                'laporan_id' => 3,
            ],


            // --- KELOMPOK HPP (HARGA POKOK PENJUALAN) ---
            [
                'name' => 'HPP Hijauan',
                'deskripsi' => 'Harga pokok modal untuk produk hijauan.',
                'laporan_id' => 4,
            ],
            [
                'name' => 'HPP Konsentrat',
                'deskripsi' => 'Harga pokok modal untuk produk konsentrat.',
                'laporan_id' => 4,
            ],
            [
                'name' => 'HPP Bahan Baku Konsentrat',
                'deskripsi' => 'Harga pokok modal untuk bahan baku konsentrat.',
                'laporan_id' => 4,
            ],
            [
                'name' => 'HPP Premix',
                'deskripsi' => 'Harga pokok modal untuk produk premix.',
                'laporan_id' => 4,
            ],
            [
                'name' => 'HPP Pakan Kucing',
                'deskripsi' => 'Harga pokok modal untuk produk pakan kucing.',
                'laporan_id' => 4,
            ],
            [
                'name' => 'HPP Obat-Obatan RMN',
                'deskripsi' => 'Harga pokok modal untuk obat ruminansia.',
                'laporan_id' => 5,
            ],
            [
                'name' => 'HPP Obat-Obatan Unggas',
                'deskripsi' => 'Harga pokok modal untuk obat unggas.',
                'laporan_id' => 5,
            ],
            [
                'name' => 'HPP Barang',
                'deskripsi' => 'Harga pokok modal untuk barang-barang.',
                'laporan_id' => 6,
            ],

            // --- KELOMPOK BEBAN OPERASIONAL ---
            [
                'name' => 'Beban Gaji Karyawan',
                'deskripsi' => 'Pengeluaran operasional untuk pembayaran upah atau gaji staf.',
                'laporan_id' => 7,
            ],
            [
                'name' => 'Beban Operasional',
                'deskripsi' => 'Kategori umum untuk biaya operasional kantor atau toko.',
                'laporan_id' => 7,
            ],

            // --- KELOMPOK ASET: BON (DARI GAMBAR 2) ---
            ['name' => 'Stok Hijauan', 'description' => 'Piutang atau tagihan pakan hijauan yang belum dibayar pelanggan.', 'laporan_id' => 8],
            ['name' => 'Stok Konsentrat', 'description' => 'Piutang atau tagihan pakan konsentrat.', 'laporan_id' => 8],
            ['name' => 'Stok Bahan Baku Konsentrat', 'description' => 'Piutang atau tagihan bahan baku konsentrat.', 'laporan_id' => 8],
            ['name' => 'Stok Premix', 'description' => 'Piutang atau tagihan produk premix.', 'laporan_id' => 8],
            ['name' => 'Stok Pakan Kucing', 'description' => 'Piutang atau tagihan pakan kucing.', 'laporan_id' => 8],
            ['name' => 'Stok Obat-Obatan RMN', 'description' => 'Piutang untuk obat ruminansia.', 'laporan_id' => 9],
            ['name' => 'Stok Obat-Obatan Unggas', 'description' => 'Piutang untuk obat unggas.', 'laporan_id' => 9],
            ['name' => 'Stok Barang', 'description' => 'Piutang untuk kategori barang umum.', 'laporan_id' => 10],
        ];

        DB::table('kategoris')->insert($categories);
    }
}