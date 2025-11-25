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
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();
            $table->string('invoice')->unique();
            $table->datetime('tanggal');
            $table->enum('type', ['Kredit', 'Debit']);
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->decimal('total', 15, 2);
            $table->decimal('uang', 15, 2)->nullable();
            $table->decimal('kembalian', 15, 2)->nullable();
            $table->enum('status', ['Lunas', 'Hutang']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};
