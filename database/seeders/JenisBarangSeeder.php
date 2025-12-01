<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisBarangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('jenis_barangs')->insert([
            [
                'name' => 'Hijauan',
                'deskripsi' => 'Kategori untuk barang berupa hijauan.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Konsentrat',
                'deskripsi' => 'Kategori untuk barang berupa konsentrat.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bahan Baku Konsentrat',
                'deskripsi' => 'Kategori untuk barang berupa bahan baku konsentrat.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Premix',
                'deskripsi' => 'Kategori untuk barang berupa premix atau suplemen.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Obat-Obatan RMN',
                'deskripsi' => 'Kategori untuk barang berupa obat-obatan atau vitamin.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Barang',
                'deskripsi' => 'Kategori untuk barang umum atau perlengkapan.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
