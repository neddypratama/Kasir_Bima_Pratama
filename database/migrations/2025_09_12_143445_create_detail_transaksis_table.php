<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_transaksis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksis');
            $table->foreignId('barang_id')->nullable()->constrained('barangs');
            $table->decimal('value', 15, 2)->nullable();
            $table->decimal('kuantitas', 10, 2)->nullable();
            $table->foreignId('satuan_id')->nullable()->constrained('konversi_satuans');
            $table->decimal('sub_total', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_transaksis');
    }
};

