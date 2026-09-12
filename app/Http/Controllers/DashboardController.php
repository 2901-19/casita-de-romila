<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $weekStart = now()->subDays(6)->startOfDay();

        // Ingresos por fecha de cobro (SalePayment.created_at), excluyendo
        // ventas anuladas. Filtro no destructivo: no borra pagos huérfanos legados.
        $payments = fn () => SalePayment::join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', '!=', 'anulada');

        $totalToday = (float) $payments()->whereDate('sale_payments.created_at', $today)
            ->sum('sale_payments.amount');

        $totalYesterday = (float) $payments()->whereDate('sale_payments.created_at', $yesterday)
            ->sum('sale_payments.amount');

        $trendPercent = $totalYesterday > 0
            ? round((($totalToday - $totalYesterday) / $totalYesterday) * 100, 1)
            : ($totalToday > 0 ? 100 : 0);

        $productsActive = Product::where('is_active', true)->count();

        $stockLow = Product::where('is_active', true)
            ->whereIn('control_type', ['inventariable', 'produccion'])
            ->whereColumn('stock_current', '<=', 'stock_min')
            ->where('stock_current', '>', 0)
            ->count();

        $stockOut = Product::where('is_active', true)
            ->whereIn('control_type', ['inventariable', 'produccion'])
            ->where('stock_current', 0)
            ->count();

        $latestRate = ExchangeRate::latest()->first();

        $recentSales = Sale::with(['user', 'payments', 'items'])
            ->where('status', 'completada')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $weeklySales = $payments()->whereDate('sale_payments.created_at', '>=', $weekStart)
            ->selectRaw('DATE(sale_payments.created_at) as day, SUM(sale_payments.amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (float) $total);

        $paymentTotals = $payments()->whereDate('sale_payments.created_at', $today)
            ->selectRaw('sale_payments.method, SUM(sale_payments.amount) as total')
            ->groupBy('sale_payments.method')
            ->pluck('total', 'method')
            ->map(fn ($total) => (float) $total);

        return view('dashboard', [
            'user' => auth()->user(),
            'totalToday' => $totalToday,
            'totalYesterday' => $totalYesterday,
            'trendPercent' => $trendPercent,
            'productsActive' => $productsActive,
            'stockLow' => $stockLow,
            'stockOut' => $stockOut,
            'latestRate' => $latestRate,
            'recentSales' => $recentSales,
            'weeklySales' => $weeklySales,
            'paymentTotals' => $paymentTotals,
        ]);
    }
}
