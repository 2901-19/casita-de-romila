<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function sales(Request $request): View
    {
        $from = $request->filled('from') ? $request->from : now()->startOfMonth()->format('Y-m-d');
        $to = $request->filled('to') ? $request->to : now()->format('Y-m-d');

        $sales = Sale::with(['user', 'payments', 'items'])
            ->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('created_at')
            ->get();

        $totalRevenue = $sales->sum('total');
        $totalTickets = $sales->count();
        $avgTicket = $totalTickets > 0 ? $totalRevenue / $totalTickets : 0;

        $byMethod = $sales
            ->flatMap->methodAmounts()
            ->groupBy('method')
            ->map(fn($group) => $group->sum('amount'));

        return view('reports.sales', compact('sales', 'totalRevenue', 'totalTickets', 'avgTicket', 'byMethod', 'from', 'to'));
    }

    public function products(Request $request): View
    {
        $from = $request->filled('from') ? $request->from : now()->startOfMonth()->format('Y-m-d');
        $to = $request->filled('to') ? $request->to : now()->format('Y-m-d');

        $saleStats = SaleItem::whereHas('sale', fn($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as total_sold, COALESCE(SUM(subtotal), 0) as total_revenue')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $products = Product::with(['category'])
            ->orderBy('name')
            ->get()
            ->map(function ($p) use ($saleStats) {
                $stat = $saleStats->get($p->id);
                $p->total_sold = $stat->total_sold ?? 0;
                $p->total_revenue = $stat->total_revenue ?? 0;
                return $p;
            })
            ->filter(fn($p) => $p->total_sold > 0 || $p->stock_current > 0);

        return view('reports.products', compact('products', 'from', 'to'));
    }

    public function credits(Request $request): View
    {
        $from = $request->filled('from') ? $request->from : now()->startOfMonth()->format('Y-m-d');
        $to = $request->filled('to') ? $request->to : now()->format('Y-m-d');

        $customers = [];
        $totalDebt = 0;

        if (class_exists(\App\Models\Customer::class)) {
            $customers = \App\Models\Customer::with(['movements' => fn($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)])
                ->orderBy('balance')
                ->get();
            $totalDebt = $customers->sum('balance');
        }

        return view('reports.credits', compact('customers', 'totalDebt', 'from', 'to'));
    }
}
