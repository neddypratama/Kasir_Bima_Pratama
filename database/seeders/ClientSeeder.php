<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    /**
     * Jalankan seeder.
     */
    public function run(): void
    {
        // PEDAGANGAN
        $clientsPedagangan = [
            'Nunur',
            'Ali',
            'Prayit',
        ];

        $clientsSupplier = [
            'Suci',
            'Seneng',
        ];

        $data = [];

        // quest
        $data[] = [
            'name' => 'Quest',
            'alamat' => 'Quest',
            'keterangan' => 'Pembeli',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($clientsPedagangan as $name) {
            $data[] = [
                'name' => $name,
                'alamat' => 'Jl. Anggrek No. ' . rand(1, 50),
                'keterangan' => 'Pembeli',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($clientsSupplier as $name) {
            $data[] = [
                'name' => $name,
                'alamat' => 'Jl. Melati No. ' . rand(1, 50),
                'keterangan' => 'Supplier',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('clients')->insert($data);
    }
}
