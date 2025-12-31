<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Middleware\RoleMiddleware;

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Volt::route('/login', 'auth/login')->name('login');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/
Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard & Profile
    Volt::route('/', 'index');
    Volt::route('/profile', 'auth/profile');

    /*
    |--------------------------------------------------------------------------
    | Admin (Role 1)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:1')->group(function () {
        Volt::route('/roles', 'roles.index');

        Volt::route('/users', 'users.index');
        Volt::route('/users/create', 'users.create');
        Volt::route('/users/{user}/edit', 'users.edit');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin & Kasir (Role 1,2)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:1,2')->group(function () {
        Volt::route('/barangs', 'barangs.index');
        Volt::route('/barangs/create', 'barangs.create');
        Volt::route('/barangs/{barang}/edit', 'barangs.edit');
        Volt::route('/barangs/{barang}/detail', 'barangs.detail');

        Volt::route('/jenisbarangs', 'jenisbarangs.index');
        Volt::route('/clients', 'clients.index');

        Volt::route('/kategori', 'kategori.index');
        Volt::route('/detail', 'detail.index');

        Volt::route('/transaksis', 'transaksis.index');
        Volt::route('/transaksis/{transaksi}/show', 'transaksis.show');

        // Stok
        Volt::route('/stok', 'stok.index');
        Volt::route('/stok/create', 'stok.create');
        Volt::route('/stok/{stok}/edit', 'stok.edit');
        Volt::route('/stok/{stok}/show', 'stok.show');
});

    /*
    |--------------------------------------------------------------------------
    | Kasir (Role 1,3)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:1,3')->group(function () {
        // Pakan
        // Volt::route('/laporan-penjualan', 'pakan.index');
        Volt::route('/kasir', 'kasir.index');
        Volt::route('/kasir/create', 'kasir.create');
        Volt::route('/kasir/{transaksi}/edit', 'kasir.edit');
        Volt::route('/kasir/{transaksi}/show', 'kasir.show');
        Volt::route('/kasir/{transaksi}/print', 'kasir.print');
    });

    Route::middleware('role:1,2')->group(function () {
        // Pakan
        // Volt::route('/laporan-penjualan', 'pakan.index');
        Volt::route('/keluar', 'keluar.index');
        Volt::route('/keluar/create', 'keluar.create');
        Volt::route('/keluar/{transaksi}/edit', 'keluar.edit');
        Volt::route('/keluar/{transaksi}/show', 'keluar.show');
        Volt::route('/keluar/{transaksi}/print', 'keluar.print');

        Volt::route('/supplier', 'supplier.index');
        Volt::route('/supplier/create', 'supplier.create');
        Volt::route('/supplier/{transaksi}/edit', 'supplier.edit');
        Volt::route('/supplier/{transaksi}/show', 'supplier.show');
        Volt::route('/supplier/{transaksi}/print', 'supplier.print');
    });

    /*
    |--------------------------------------------------------------------------
    | Laporan (Role 1,2)
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:1,2')->group(function () {
        Volt::route('/laporan-labarugi', 'laporan.labarugi');
        Volt::route('/laporan-aset', 'laporan.aset');
    });
});
