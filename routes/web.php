<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LanzadorController;
use App\Http\Controllers\MermaController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'))->middleware('auth');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::post('/lanzador/cerrar-sesion', [LanzadorController::class, 'cerrarSesion'])
    ->middleware('throttle:10,1')
    ->name('lanzador.cerrar-sesion');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('can:manage-products')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::post('products/bulk-toggle', [ProductController::class, 'bulkToggle'])->name('products.bulk-toggle');
        Route::delete('products/bulk-delete', [ProductController::class, 'bulkDelete'])->name('products.bulk-delete');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('combos', [ComboController::class, 'index'])->name('combos.index');
        Route::get('combos/create', [ComboController::class, 'create'])->name('combos.create');
        Route::post('combos', [ComboController::class, 'store'])->name('combos.store');
        Route::get('combos/{combo}/edit', [ComboController::class, 'edit'])->name('combos.edit');
        Route::put('combos/{combo}', [ComboController::class, 'update'])->name('combos.update');
        Route::patch('combos/{combo}/toggle-active', [ComboController::class, 'toggleActive'])->name('combos.toggle-active');
        Route::delete('combos/{combo}', [ComboController::class, 'destroy'])->name('combos.destroy');
    });

    Route::middleware('can:manage-inventory')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::get('productions', [ProductionController::class, 'index'])->name('productions.index');
        Route::post('productions', [ProductionController::class, 'store'])->name('productions.store');
        Route::delete('productions/{production}', [ProductionController::class, 'destroy'])->name('productions.destroy');
    });

    Route::middleware('can:manage-waste')->group(function () {
        Route::get('mermas', [MermaController::class, 'index'])->name('mermas.index');
        Route::post('mermas', [MermaController::class, 'store'])->name('mermas.store');
    });

    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [PosController::class, 'store'])->name('pos.store');

    Route::get('comandas', [ComandaController::class, 'index'])->name('comandas.index');
    Route::get('comandas/create', [ComandaController::class, 'create'])->name('comandas.create');
    Route::post('comandas', [ComandaController::class, 'store'])->name('comandas.store');
    Route::get('comandas/{comanda}', [ComandaController::class, 'show'])->name('comandas.show');
    Route::put('comandas/{comanda}', [ComandaController::class, 'update'])->name('comandas.update');
    Route::patch('comandas/{comanda}/entregar', [ComandaController::class, 'markDelivered'])->name('comandas.mark-delivered');
    Route::patch('comandas/{comanda}/items/{item}/entregar', [ComandaController::class, 'deliverItem'])->name('comandas.deliver-item');
    Route::post('comandas/{comanda}/cobrar', [ComandaController::class, 'collect'])->name('comandas.collect');

    Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('sales/{sale}', [SalesController::class, 'show'])->name('sales.show');
    Route::delete('sales/{sale}', [SalesController::class, 'destroy'])->name('sales.destroy')->middleware('can:void-sales');

    Route::middleware('can:manage-credits')->group(function () {
        Route::get('credits', [CustomerController::class, 'index'])->name('credits.index');
        Route::post('credits', [CustomerController::class, 'store'])->name('credits.store');
        Route::get('credits/{customer}', [CustomerController::class, 'show'])->name('credits.show');
        Route::put('credits/{customer}', [CustomerController::class, 'update'])->name('credits.update');
        Route::post('credits/{customer}/credits/{sale}/pay', [CustomerController::class, 'payCredit'])->name('credits.pay');
    });

    Route::middleware('can:manage-exchange-rates')->group(function () {
        Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
    });

    Route::middleware('can:view-reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/products', [ReportController::class, 'products'])->name('reports.products');
        Route::get('reports/credits', [ReportController::class, 'credits'])->name('reports.credits');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});
