<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $weekStart = now()->subDays(6)->startOfDay();

        $totalToday = (float) Sale::where('status', 'completada')
            ->whereDate('created_at', $today)
            ->sum('total');

        $totalYesterday = (float) Sale::where('status', 'completada')
            ->whereDate('created_at', $yesterday)
            ->sum('total');

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

        $weeklySales = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $weekStart)
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn($total) => (float) $total);

        $paymentTotals = Sale::where('sales.status', 'completada')
            ->whereDate('sales.created_at', $today)
            ->whereNull('sales.payment_method')
            ->join('sale_payments', 'sales.id', '=', 'sale_payments.sale_id')
            ->selectRaw('sale_payments.method, SUM(sale_payments.amount) as total')
            ->groupBy('sale_payments.method')
            ->pluck('total', 'method')
            ->map(fn($total) => (float) $total);

        $creditTotal = (float) Sale::where('status', 'completada')
            ->where('payment_method', 'credito')
            ->whereDate('created_at', $today)
            ->sum('total');
        $paymentTotals['credito'] = $creditTotal;

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
