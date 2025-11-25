<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Cropper.js --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />

    {{-- TinyMCE --}}
    <script src="https://cdn.tiny.cloud/1/zj7w29mcgsahkxloyg71v6365yxaoa4ey1ur6l45pnb63v42/tinymce/6/tinymce.min.js"
        referrerpolicy="origin"></script>

    {{--  Currency  --}}
    <script type="text/javascript" src="https://cdn.jsdelivr.net/gh/robsontenorio/mary@0.44.2/libs/currency/currency.js">
    </script>

    {{-- Chart.js  --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body class="min-h-screen font-sans antialiased bg-base-200">

    {{-- NAVBAR mobile only --}}
    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <x-app-brand class="px-5 pt-4" />

            {{-- MENU --}}
            <x-menu activate-by-route>

                {{-- User --}}
                @if ($user = auth()->user())
                    <x-menu-separator />
                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover
                        class="-mx-2 !-my-2 rounded">
                        <x-slot:actions>
                            <x-dropdown>
                                <x-slot:trigger>
                                    <x-button icon="fas.gear" class="btn-circle btn-ghost" />
                                </x-slot:trigger>

                                <div class="grid grid-rows-3 grid-flow-col gap-4">
                                    <x-button label="Logout" icon="fas.power-off" link="/logout" responsive />
                                    <x-theme-toggle class="btn" label="Theme" responsive />
                                    <x-button label="Profil" icon="fas.user" link="/profile" responsive />
                                </div>
                            </x-dropdown>
                        </x-slot:actions>
                    </x-list-item>
                    <x-menu-separator />
                @endif

                <x-menu-item title="Dashboard" icon="fas.house" link="/" />

                {{-- ✅ User Management hanya untuk role 1 (Admin) --}}
                @if (auth()->user()->role_id == 1)
                    <x-menu-sub title="User Management" icon="fas.users-gear">
                        <x-menu-item title="User" icon="fas.user" link="/users" />
                        <x-menu-item title="Role" icon="fas.user-shield" link="/roles" />
                    </x-menu-sub>
                @endif

                {{-- ✅ Master Data hanya untuk role 1 dan 2 --}}
                @if (in_array(auth()->user()->role_id, [1, 2]))
                    <x-menu-sub title="Master Data" icon="fas.database">
                        <x-menu-item title="Jenis Barang" icon="fas.archive" link="/jenisbarangs" />
                        <x-menu-item title="Barang" icon="fas.box" link="/barangs" />
                        <x-menu-item title="Klien" icon="fas.users" link="/clients" />
                        <x-menu-item title="Transaksi" icon="fas.cart-shopping" link="/transaksis" />
                    </x-menu-sub>
                @endif

                {{-- ✅ Stok hanya untuk role 1 dan 2 --}}
                @if (in_array(auth()->user()->role_id, [1, 2]))
                    <x-menu-sub title="Manage Stok" icon="fas.warehouse">
                        {{-- <x-menu-item title="Laporan Stok" icon="fas.file" link="/pakan" /> --}}
                        <x-menu-item title="Stok" icon="fas.capsules" link="/stok" />
                    </x-menu-sub>
                @endif

                <x-menu-sub title="Pakan & Obat" icon="fas.flask">
                    @if (in_array(auth()->user()->role_id, [1, 3]))
                        {{-- <x-menu-item title="Laporan Penjualan" icon="fas.store" link="/laporan-penjualan" /> --}}
                        <x-menu-item title="Kasir" icon="fas.cart-plus" link="/kasir" />
                        <x-menu-item title="Pembelian Stok" icon="fas.file-invoice-dollar" link="/supplier" />
                    @endif
                </x-menu-sub>

                @if (in_array(auth()->user()->role_id, [1, 2]))
                    <x-menu-sub title="Laporan" icon="fas.chart-bar">
                        <x-menu-item title="Laporan Laba Rugi" icon="fas.money-bill-transfer"
                            link="/laporan-labarugi" />
                        <x-menu-item title="Laporan Aset" icon="fas.chart-simple" link="/laporan-aset" />
                        <x-menu-item title="Laporan Neraca Saldo" icon="fas.scale-balanced"
                            link="/laporan-neraca-saldo" />
                    </x-menu-sub>
                @endif

            </x-menu>
        </x-slot:sidebar>

        {{-- Content --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    {{-- TOAST area --}}
    <x-toast />
</body>

</html>
