<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stok_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('barang_id')->constrained('barangs');
            $table->foreignId('detail_transaksi_id')->nullable()->constrained('detail_transaksis');
            $table->decimal('qty_masuk', 10, 2);
            $table->decimal('qty_sisa', 10, 2);
            $table->decimal('harga', 15, 2);
            $table->timestamp('tanggal');
            $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stok_batches');
    }
};
