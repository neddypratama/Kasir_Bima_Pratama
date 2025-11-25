<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisBarangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {
        
        DB::table('jenis_barangs')->insert([
            [
                'name' => 'Obat-Obatan',
                'deskripsi' => 'Kategori untuk barang berupa obat-obatan atau vitamin.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pakan Sentrat/Pabrikan',
                'deskripsi' => 'Kategori untuk barang pakan atau sentrat ternak.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pakan Curah',
                'deskripsi' => 'Kategori untuk barang pakan curah.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pakan Kucing',
                'deskripsi' => 'Kategori untuk barang pakan Kucing.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
