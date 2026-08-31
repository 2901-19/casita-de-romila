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

        $salesToday = Sale::where('status', 'completada')
            ->whereDate('created_at', $today)
            ->with('payments')
            ->get();

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
            ->whereColumn('stock_current', '<=', 'stock_min')
            ->where('stock_current', '>', 0)
            ->count();

        $stockOut = Product::where('is_active', true)
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
            ->get(['created_at', 'total'])
            ->groupBy(fn($s) => $s->created_at->format('Y-m-d'))
            ->map(fn($group) => (float) $group->sum('total'));

        $paymentTotals = $salesToday
            ->flatMap->methodAmounts()
            ->groupBy('method')
            ->map(fn($group) => $group->sum('amount'));

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
