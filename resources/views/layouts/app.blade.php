<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Casita de Romila')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app" x-data>
        {{-- Sidebar --}}
        <aside class="sidebar d-none d-lg-flex">
            <div class="sidebar-logo">
                <div class="logo-mark">
                    <i class="bi bi-house-heart-fill"></i>
                </div>
                <span class="brand">Casita de Romila</span>
            </div>

            <nav class="sidebar-nav">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-grid-1x2-fill"></i> Dashboard
                </a>
                @can('manage-products')
                <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">
                    <i class="bi bi-box-seam"></i> Productos
                </a>
                @endcan
                @can('manage-products')
                <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}">
                    <i class="bi bi-tags"></i> Categorías
                </a>
                @endcan
                @can('manage-products')
                <a class="nav-link {{ request()->routeIs('combos.*') ? 'active' : '' }}" href="{{ route('combos.index') }}">
                    <i class="bi bi-box-seam"></i> Combos
                </a>
                @endcan
                @can('manage-inventory')
                <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                    <i class="bi bi-clipboard2-data"></i> Inventario
                </a>
                @endcan
                @can('manage-inventory')
                <a class="nav-link {{ request()->routeIs('productions.*') ? 'active' : '' }}" href="{{ route('productions.index') }}">
                    <i class="bi bi-tools"></i> Producción
                </a>
                @endcan
                @can('manage-waste')
                <a class="nav-link {{ request()->routeIs('mermas.*') ? 'active' : '' }}" href="{{ route('mermas.index') }}">
                    <i class="bi bi-exclamation-triangle"></i> Mermas
                </a>
                @endcan
                <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">
                    <i class="bi bi-cart4"></i> POS / Ventas
                </a>
                <a class="nav-link {{ request()->routeIs('comandas.*') ? 'active' : '' }}" href="{{ route('comandas.index') }}">
                    <i class="bi bi-journal-text"></i> Comandas
                </a>
                <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">
                    <i class="bi bi-receipt"></i> Historial
                </a>
                @can('manage-credits')
                <a class="nav-link {{ request()->routeIs('credits.*') ? 'active' : '' }}" href="{{ route('credits.index') }}">
                    <i class="bi bi-credit-card"></i> Créditos
                </a>
                @endcan
                @can('manage-exchange-rates')
                <a class="nav-link {{ request()->routeIs('exchange-rates.*') ? 'active' : '' }}" href="{{ route('exchange-rates.index') }}">
                    <i class="bi bi-currency-exchange"></i> Tasa BCV
                </a>
                @endcan
                @can('view-reports')
                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                    <i class="bi bi-bar-chart-line"></i> Reportes
                </a>
                @endcan
                @can('manage-users')
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-people"></i> Usuarios
                </a>
                @endcan
            </nav>

            <div class="sidebar-user">
                <span class="avatar">{{ auth()->user()->name[0] }}</span>
                <span class="flex-grow-1">
                    <span class="user-name">{{ auth()->user()->name }}</span>
                    <span class="user-role">{{ auth()->user()->role?->name }}</span>
                </span>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-icon" title="Cerrar sesión" aria-label="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="app-main">
            <header class="topbar d-flex align-items-center">
                <button class="btn btn-icon d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="topbar-title mb-0">@yield('title', 'Dashboard')</h1>

                <div class="ms-auto d-flex align-items-center gap-2">
                    @yield('topbar-actions')
                </div>
            </header>

            <main class="content p-3 p-lg-4">
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Mobile sidebar (offcanvas) --}}
    <div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="mobileSidebar">
        <div class="offcanvas-header">
            <span class="brand">Casita de Romila</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column">
            <nav class="sidebar-nav">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-grid-1x2-fill"></i> Dashboard
                </a>
                @can('manage-products')
                <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">
                    <i class="bi bi-box-seam"></i> Productos
                </a>
                @endcan
                @can('manage-products')
                <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}">
                    <i class="bi bi-tags"></i> Categorías
                </a>
                @endcan
                @can('manage-products')
                <a class="nav-link {{ request()->routeIs('combos.*') ? 'active' : '' }}" href="{{ route('combos.index') }}">
                    <i class="bi bi-box-seam"></i> Combos
                </a>
                @endcan
                @can('manage-inventory')
                <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                    <i class="bi bi-clipboard2-data"></i> Inventario
                </a>
                @endcan
                @can('manage-inventory')
                <a class="nav-link {{ request()->routeIs('productions.*') ? 'active' : '' }}" href="{{ route('productions.index') }}">
                    <i class="bi bi-tools"></i> Producción
                </a>
                @endcan
                @can('manage-waste')
                <a class="nav-link {{ request()->routeIs('mermas.*') ? 'active' : '' }}" href="{{ route('mermas.index') }}">
                    <i class="bi bi-exclamation-triangle"></i> Mermas
                </a>
                @endcan
                <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">
                    <i class="bi bi-cart4"></i> POS / Ventas
                </a>
                <a class="nav-link {{ request()->routeIs('comandas.*') ? 'active' : '' }}" href="{{ route('comandas.index') }}">
                    <i class="bi bi-journal-text"></i> Comandas
                </a>
                <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}">
                    <i class="bi bi-receipt"></i> Historial
                </a>
                @can('manage-credits')
                <a class="nav-link {{ request()->routeIs('credits.*') ? 'active' : '' }}" href="{{ route('credits.index') }}">
                    <i class="bi bi-credit-card"></i> Créditos
                </a>
                @endcan
                @can('manage-exchange-rates')
                <a class="nav-link {{ request()->routeIs('exchange-rates.*') ? 'active' : '' }}" href="{{ route('exchange-rates.index') }}">
                    <i class="bi bi-currency-exchange"></i> Tasa BCV
                </a>
                @endcan
                @can('view-reports')
                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                    <i class="bi bi-bar-chart-line"></i> Reportes
                </a>
                @endcan
                @can('manage-users')
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-people"></i> Usuarios
                </a>
                @endcan
            </nav>
            <div class="sidebar-user">
                <span class="avatar">{{ auth()->user()->name[0] }}</span>
                <span class="flex-grow-1">
                    <span class="user-name">{{ auth()->user()->name }}</span>
                    <span class="user-role">{{ auth()->user()->role?->name }}</span>
                </span>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-icon" title="Cerrar sesión" aria-label="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    @yield('modals')

    @if(session('success') || session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
                window.toast.fire({ icon: 'success', title: @json(session('success')) });
            @endif
            @if(session('error'))
                window.toast.fire({ icon: 'error', title: @json(session('error')) });
            @endif
        });
    </script>
    @endif

    @stack('charts')

    @stack('scripts')
</body>
</html>
