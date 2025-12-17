<?php

namespace Database\Seeders;

use App\Models\JenisBarang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenisBarangIds = DB::table('jenis_barangs')->pluck('id', 'name')->toArray();

        $barangs = [
            'Hijauan' => [
                'TEBON JAGUNG',
                'KOLONJONO',
                'SILASE TEBON PLUS JAGUNG',
                'SILASE PAKCHONG',
                'COMPLETE FEED',
            ],
            'Konsentrat' => [
                'SMG S 18',
                'SMG S 20',
                'SMG S 22',
                'BARAKA B18',
                'KALVOLAC',
            ],
            'Bahan Baku Konsentrat' => [
                'BKK','CGF MIWON','CGF CARGILL','DDGS','B. KOPRA','B. SAWIT','POLAR ANGSA',
                'POLAR TONGKAT','KULIT KOPI','CGM','PELET WILMAR','AMPAS KECAP','AMPAS BIR',
                'TUMPI JAGUNG','KATUL LEDOK','KATUL SPARATOR','KANGKUNG KERING','PONGKOL A',
                'PONGKOL B','KULIT KACANG HIJAU','KLECEPAN DELE','GAMBLONG','KACANG HIJAU GILING',
                'FML AJINOMOTO','TETES TEBU',
            ],
            'Premix' => [
                'TEMULAWAK','KUNIR','JAHE','KENCUR','MENGKUDU','VETWAYS PUTIH','VETWAYS MINERAL HITAM',
                'MINERAL BLOCK ROYAL 3KG','MINERAL BLOCK ROYAL 2KG','EM4 PETERNAKAN','EM4 PERIKANAN','EM4 PERTANIAN',
            ],
            'Obat-Obatan RMN' => [
                'B COMPLEX','MEDOXY LA','SULPIDON','LIMOXIN LA','BIOSAN TP','CALCIDEX','INTERFLOX',
                'OBAT CACING','TYMPANOL','VITOL','INJECTAMIN','COLIBACT','WORMECTIN','INTERMECTIN','GUSANEX','BETADINE',
            ],
            'Barang' => [
                'SUNTIK 5 ML','SUNTIK 1 ML','SPET 30ML','DOT CEMPE','WADAH AIR MINUM',
            ],
        ];



        foreach ($barangs as $jenis => $listBarang) {
            foreach ($listBarang as $barang) {
                DB::table('barangs')->insert([
                    'jenis_id' => $jenisBarangIds[$jenis],
                    'name' => $barang,
                    'stok' => 0,
                    'satuan' => 'Kg',
                    'hpp' => 0,
                    'harga' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
