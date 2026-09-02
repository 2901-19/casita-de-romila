<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Merma;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Traits\ExportableCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    use ExportableCsv;

    protected function dateRange(Request $request): array
    {
        return [
            $request->filled('from') ? $request->from : now()->startOfMonth()->format('Y-m-d'),
            $request->filled('to') ? $request->to : now()->format('Y-m-d'),
        ];
    }

    public function index(): View
    {
        [$from, $to] = $this->dateRange(new Request);

        $monthRevenue = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', now()->startOfMonth())
            ->whereDate('created_at', '<=', now())
            ->sum('total');

        $activeProducts = Product::active()->count();

        $pendingCredit = Customer::where('balance', '<', 0)->sum('balance');

        $totalWaste = Merma::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('quantity');

        return view('reports.index', compact('monthRevenue', 'activeProducts', 'pendingCredit', 'totalWaste', 'from', 'to'));
    }

    // ─── Ventas ─────────────────────────────────────────────

    public function sales(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $sales = Sale::with(['user', 'payments', 'items'])
            ->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $totals = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('COUNT(*) as tickets, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $totalRevenue = (float) $totals->revenue;
        $totalTickets = (int) $totals->tickets;
        $avgTicket = $totalTickets > 0 ? $totalRevenue / $totalTickets : 0;

        $byMethod = Sale::where('sales.status', 'completada')
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->whereNull('sales.payment_method')
            ->join('sale_payments', 'sales.id', '=', 'sale_payments.sale_id')
            ->selectRaw('sale_payments.method, SUM(sale_payments.amount) as total')
            ->groupBy('sale_payments.method')
            ->pluck('total', 'method');

        $creditTotal = Sale::where('status', 'completada')
            ->where('payment_method', 'credito')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('total');
        $byMethod->put('credito', $creditTotal);

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);
        $totalUsd = $rate > 0 ? $totalRevenue / $rate : 0;

        return view('reports.sales', compact('sales', 'totalRevenue', 'totalTickets', 'avgTicket', 'totalUsd', 'byMethod', 'from', 'to'));
    }

    public function salesExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $sales = Sale::with(['payments'])
            ->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderByDesc('created_at')
            ->get();

        $methodLabels = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transferencia', 'pago_movil' => 'Pago Movil', 'pdv' => 'PDV', 'credito' => 'Credito'];

        $rows = $sales->map(fn ($s) => [
            $s->id,
            $s->created_at->format('d/m/Y h:i a'),
            $s->items_count ?? $s->items()->count(),
            number_format($s->total, 2, ',', '.'),
            $methodLabels[$s->payment_method] ?? ($methodLabels[$s->payments->first()?->method] ?? '—'),
        ]);

        return $this->exportCsv(
            ['#', 'Fecha', 'Items', 'Total (Bs)', 'Metodo'],
            $rows,
            "ventas_{$from}_{$to}.csv"
        );
    }

    // ─── Productos ──────────────────────────────────────────

    public function products(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $saleStats = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('sale_items.product_id,
                COALESCE(SUM(sale_items.quantity), 0) as total_sold,
                COALESCE(SUM(sale_items.subtotal), 0) as total_revenue,
                COALESCE(SUM(sale_items.quantity * products.cost_price), 0) as total_cost')
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy('product_id');

        $products = Product::with(['category'])
            ->orderBy('name')
            ->get()
            ->map(function ($p) use ($saleStats) {
                $stat = $saleStats->get($p->id);
                $p->total_sold = $stat->total_sold ?? 0;
                $p->total_revenue = $stat->total_revenue ?? 0;
                $p->total_cost = $stat->total_cost ?? 0;
                $p->profit = round($p->total_revenue - $p->total_cost, 2);
                return $p;
            })
            ->filter(fn ($p) => $p->total_sold > 0 || $p->stock_current > 0);

        return view('reports.products', compact('products', 'from', 'to'));
    }

    public function productsExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $saleStats = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('sale_items.product_id,
                COALESCE(SUM(sale_items.quantity), 0) as total_sold,
                COALESCE(SUM(sale_items.subtotal), 0) as total_revenue,
                COALESCE(SUM(sale_items.quantity * products.cost_price), 0) as total_cost')
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy('product_id');

        $products = Product::with(['category'])
            ->orderBy('name')
            ->get()
            ->map(function ($p) use ($saleStats) {
                $stat = $saleStats->get($p->id);
                $p->total_sold = $stat->total_sold ?? 0;
                $p->total_revenue = $stat->total_revenue ?? 0;
                $p->total_cost = $stat->total_cost ?? 0;
                $p->profit = round($p->total_revenue - $p->total_cost, 2);
                return $p;
            })
            ->filter(fn ($p) => $p->total_sold > 0 || $p->stock_current > 0);

        $rows = $products->map(fn ($p) => [
            $p->name,
            $p->category->name ?? '—',
            $p->control_type,
            $p->total_sold,
            number_format($p->total_revenue, 2, ',', '.'),
            number_format($p->profit, 2, ',', '.'),
            $p->stock_current,
        ]);

        return $this->exportCsv(
            ['Producto', 'Categoria', 'Tipo', 'Vendidos', 'Revenue (Bs)', 'Ganancia (Bs)', 'Stock'],
            $rows,
            "productos_{$from}_{$to}.csv"
        );
    }

    // ─── Creditos ───────────────────────────────────────────

    public function credits(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $customers = Customer::with(['movements' => fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)])
            ->whereHas('movements', fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to))
            ->orderBy('balance')
            ->get();

        $totalDebt = $customers->sum('balance');

        return view('reports.credits', compact('customers', 'totalDebt', 'from', 'to'));
    }

    public function creditsExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $customers = Customer::with(['movements' => fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)])
            ->whereHas('movements', fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to))
            ->orderBy('balance')
            ->get();

        $rows = $customers->map(fn ($c) => [
            $c->name,
            $c->phone ?? '—',
            number_format($c->balance, 2, ',', '.'),
            $c->balance < 0 ? 'Debe' : ($c->balance > 0 ? 'A favor' : 'Al dia'),
            $c->movements->last()?->created_at?->format('d/m/Y H:i') ?? '—',
        ]);

        return $this->exportCsv(
            ['Cliente', 'Telefono', 'Saldo (Bs)', 'Estado', 'Ultimo movimiento'],
            $rows,
            "creditos_{$from}_{$to}.csv"
        );
    }

    // ─── Dias con mas ventas ────────────────────────────────

    public function topDays(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $topDays = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as tickets, SUM(total) as revenue')
            ->groupBy('day')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($d) => [
                'day' => $d->day,
                'tickets' => (int) $d->tickets,
                'revenue' => (float) $d->revenue,
                'avg' => $d->tickets > 0 ? $d->revenue / $d->tickets : 0,
            ]);

        return view('reports.top-days', compact('topDays', 'from', 'to'));
    }

    public function topDaysExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $topDays = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as tickets, SUM(total) as revenue')
            ->groupBy('day')
            ->orderByDesc('revenue')
            ->get();

        $rows = $topDays->map(fn ($d) => [
            $d->day,
            $d->tickets,
            number_format($d->revenue, 2, ',', '.'),
            $d->tickets > 0 ? number_format($d->revenue / $d->tickets, 2, ',', '.') : '0,00',
        ]);

        return $this->exportCsv(
            ['Dia', 'Tickets', 'Revenue (Bs)', 'Promedio/Ticket (Bs)'],
            $rows,
            "dias_top_{$from}_{$to}.csv"
        );
    }

    // ─── Mermas ─────────────────────────────────────────────

    public function waste(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $waste = Merma::with('product')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as total_wasted, MAX(created_at) as last_date')
            ->groupBy('product_id')
            ->orderByDesc('total_wasted')
            ->get()
            ->map(function ($w) {
                $w->last_date = $w->last_date ? \Carbon\Carbon::parse($w->last_date) : null;
                return [
                    'product' => $w->product,
                    'total_wasted' => (int) $w->total_wasted,
                    'last_date' => $w->last_date,
                ];
            });

        $byReason = Merma::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('reason, SUM(quantity) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get();

        $totalWaste = $waste->sum('total_wasted');

        return view('reports.waste', compact('waste', 'byReason', 'totalWaste', 'from', 'to'));
    }

    public function wasteExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $waste = Merma::with('product')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as total_wasted, MAX(created_at) as last_date')
            ->groupBy('product_id')
            ->orderByDesc('total_wasted')
            ->get();

        $reasonLabels = ['vencido' => 'Vencido', 'danado' => 'Danado', 'otro' => 'Otro'];

        $rows = $waste->map(fn ($w) => [
            $w->product?->name ?? '—',
            $w->total_wasted,
            $reasonLabels[$w->reason] ?? ucfirst($w->reason ?? ''),
            $w->last_date?->format('d/m/Y H:i') ?? '—',
        ]);

        return $this->exportCsv(
            ['Producto', 'Cantidad', 'Motivo', 'Fecha ultima merma'],
            $rows,
            "mermas_{$from}_{$to}.csv"
        );
    }

    // ─── Margen de ganancia ─────────────────────────────────

    public function profitMargin(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $saleStats = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('sale_items.product_id,
                SUM(sale_items.quantity) as sold,
                SUM(sale_items.subtotal) as revenue,
                SUM(sale_items.quantity * products.cost_price) as cost')
            ->groupBy('sale_items.product_id')
            ->get();

        $products = $saleStats->map(function ($s) {
            $s->profit = round($s->revenue - $s->cost, 2);
            $s->margin_pct = $s->revenue > 0 ? round(($s->profit / $s->revenue) * 100, 1) : 0;
            $s->product = Product::with('category')->find($s->product_id);
            return $s;
        })->sortByDesc('profit')->values();

        $totalRevenue = $products->sum('revenue');
        $totalCost = $products->sum('cost');
        $totalProfit = round($totalRevenue - $totalCost, 2);

        return view('reports.profit-margin', compact('products', 'totalRevenue', 'totalCost', 'totalProfit', 'from', 'to'));
    }

    public function profitMarginExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $saleStats = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('sale_items.product_id,
                SUM(sale_items.quantity) as sold,
                SUM(sale_items.subtotal) as revenue,
                SUM(sale_items.quantity * products.cost_price) as cost')
            ->groupBy('sale_items.product_id')
            ->get();

        $rows = $saleStats->map(function ($s) {
            $profit = round($s->revenue - $s->cost, 2);
            $margin = $s->revenue > 0 ? round(($profit / $s->revenue) * 100, 1) : 0;
            $product = Product::with('category')->find($s->product_id);
            return [
                $product?->name ?? '—',
                $product?->category?->name ?? '—',
                $s->sold,
                number_format($s->revenue, 2, ',', '.'),
                number_format($s->cost, 2, ',', '.'),
                number_format($profit, 2, ',', '.'),
                "{$margin}%",
            ];
        });

        return $this->exportCsv(
            ['Producto', 'Categoria', 'Vendidos', 'Revenue (Bs)', 'Costo (Bs)', 'Ganancia (Bs)', 'Margen %'],
            $rows,
            "margen_ganancia_{$from}_{$to}.csv"
        );
    }

    // ─── Ventas por horario ─────────────────────────────────

    public function salesBySchedule(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $bySchedule = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("CASE WHEN CAST(strftime('%H', sales.created_at) AS INTEGER) < 15 THEN 'manana' ELSE 'noche' END as schedule,
                COUNT(*) as tickets, SUM(sales.total) as revenue")
            ->groupBy('schedule')
            ->get()
            ->keyBy('schedule');

        $manana = $bySchedule->get('manana');
        $noche = $bySchedule->get('noche');

        $totalTickets = ($manana->tickets ?? 0) + ($noche->tickets ?? 0);
        $totalRevenue = ($manana->revenue ?? 0) + ($noche->revenue ?? 0);

        return view('reports.sales-by-schedule', compact('manana', 'noche', 'totalTickets', 'totalRevenue', 'from', 'to'));
    }

    public function salesByScheduleExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $bySchedule = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("CASE WHEN CAST(strftime('%H', sales.created_at) AS INTEGER) < 15 THEN 'Manana (antes 3pm)' ELSE 'Noche (despues 3pm)' END as schedule,
                COUNT(*) as tickets, SUM(sales.total) as revenue")
            ->groupBy('schedule')
            ->get();

        $rows = $bySchedule->map(fn ($s) => [
            $s->schedule,
            $s->tickets,
            number_format($s->revenue, 2, ',', '.'),
            $s->tickets > 0 ? number_format($s->revenue / $s->tickets, 2, ',', '.') : '0,00',
        ]);

        return $this->exportCsv(
            ['Horario', 'Tickets', 'Revenue (Bs)', 'Promedio/Ticket (Bs)'],
            $rows,
            "ventas_horario_{$from}_{$to}.csv"
        );
    }

    // ─── Productos de lento movimiento ──────────────────────

    public function slowMovers(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $days = (int) $request->input('days', 30);

        $activeProductIds = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada'))
            ->whereDate('sale_items.created_at', '>=', now()->subDays($days))
            ->pluck('product_id')
            ->unique();

        $products = Product::with('category')
            ->active()
            ->get()
            ->map(function ($p) use ($activeProductIds) {
                $p->is_slow = ! $activeProductIds->contains($p->id);
                $p->lastSale = SaleItem::where('product_id', $p->id)
                    ->whereHas('sale', fn ($q) => $q->where('status', 'completada'))
                    ->max('sale_items.created_at');
                $p->daysSinceSale = $p->lastSale ? now()->diffInDays($p->lastSale) : null;
                return $p;
            })
            ->filter(fn ($p) => $p->is_slow)
            ->sortBy('daysSinceSale', SORT_DESC, true)
            ->values();

        return view('reports.slow-movers', compact('products', 'days', 'from', 'to'));
    }

    public function slowMoversExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $days = (int) $request->input('days', 30);

        $activeProductIds = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada'))
            ->whereDate('sale_items.created_at', '>=', now()->subDays($days))
            ->pluck('product_id')
            ->unique();

        $products = Product::with('category')
            ->active()
            ->get()
            ->map(function ($p) use ($activeProductIds) {
                $p->is_slow = ! $activeProductIds->contains($p->id);
                $p->lastSale = SaleItem::where('product_id', $p->id)
                    ->whereHas('sale', fn ($q) => $q->where('status', 'completada'))
                    ->max('sale_items.created_at');
                $p->daysSinceSale = $p->lastSale ? now()->diffInDays($p->lastSale) : null;
                return $p;
            })
            ->filter(fn ($p) => $p->is_slow);

        $rows = $products->map(fn ($p) => [
            $p->name,
            $p->category->name ?? '—',
            $p->control_type,
            $p->lastSale ? now()->diffInDays($p->lastSale) : 'Nunca',
            $p->stock_current,
        ]);

        return $this->exportCsv(
            ['Producto', 'Categoria', 'Tipo', 'Dias sin venta', 'Stock actual'],
            $rows,
            "lento_movimiento_{$days}d_{$from}_{$to}.csv"
        );
    }

    // ─── Rendimiento por dia de la semana ───────────────────

    public function weeklyPerformance(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $dayLabels = [0 => 'Lunes', 1 => 'Martes', 2 => 'Miercoles', 3 => 'Jueves', 4 => 'Viernes', 5 => 'Sabado', 6 => 'Domingo'];

        $byDay = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("CASE CAST(strftime('%w', sales.created_at) AS INTEGER) WHEN 0 THEN 6 ELSE CAST(strftime('%w', sales.created_at) AS INTEGER) - 1 END as dow,
                COUNT(*) as tickets, SUM(sales.total) as revenue")
            ->groupBy('dow')
            ->get()
            ->map(function ($d) use ($dayLabels) {
                $d->day_name = $dayLabels[(int) $d->dow] ?? '—';
                $d->avg = $d->tickets > 0 ? $d->revenue / $d->tickets : 0;
                return $d;
            })
            ->sortBy('dow')
            ->values();

        return view('reports.weekly-performance', compact('byDay', 'from', 'to'));
    }

    public function weeklyPerformanceExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $dayLabels = [0 => 'Lunes', 1 => 'Martes', 2 => 'Miercoles', 3 => 'Jueves', 4 => 'Viernes', 5 => 'Sabado', 6 => 'Domingo'];

        $byDay = Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("CASE CAST(strftime('%w', sales.created_at) AS INTEGER) WHEN 0 THEN 6 ELSE CAST(strftime('%w', sales.created_at) AS INTEGER) - 1 END as dow,
                COUNT(*) as tickets, SUM(sales.total) as revenue")
            ->groupBy('dow')
            ->get()
            ->map(function ($d) use ($dayLabels) {
                return [
                    $dayLabels[(int) $d->dow] ?? '—',
                    $d->tickets,
                    number_format($d->revenue, 2, ',', '.'),
                    $d->tickets > 0 ? number_format($d->revenue / $d->tickets, 2, ',', '.') : '0,00',
                ];
            })
            ->sortBy(fn ($r) => array_search($r[0], $dayLabels, true));

        return $this->exportCsv(
            ['Dia', 'Tickets', 'Revenue (Bs)', 'Promedio/Ticket (Bs)'],
            $rows = $byDay->values(),
            "rendimiento_semanal_{$from}_{$to}.csv"
        );
    }

    // ─── Produccion vs Ventas vs Mermas ─────────────────────

    public function productionVsSales(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $produced = Production::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as produced')
            ->groupBy('product_id')
            ->pluck('produced', 'product_id');

        $sold = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')
            ->pluck('sold', 'product_id');

        $wasted = Merma::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as wasted')
            ->groupBy('product_id')
            ->pluck('wasted', 'product_id');

        $allProductIds = $produced->keys()->merge($sold->keys())->merge($wasted->keys())->unique();

        $comparison = Product::whereIn('id', $allProductIds)->with('category')->get()->map(function ($p) use ($produced, $sold, $wasted) {
            $p->produced = $produced->get($p->id, 0);
            $p->sold = $sold->get($p->id, 0);
            $p->wasted = $wasted->get($p->id, 0);
            $p->efficiency = $p->produced > 0 ? round(($p->sold / $p->produced) * 100, 1) : 0;
            return $p;
        })->sortByDesc('sold')->values();

        return view('reports.production-vs-sales', compact('comparison', 'from', 'to'));
    }

    public function productionVsSalesExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $produced = Production::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as produced')
            ->groupBy('product_id')
            ->pluck('produced', 'product_id');

        $sold = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')
            ->pluck('sold', 'product_id');

        $wasted = Merma::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as wasted')
            ->groupBy('product_id')
            ->pluck('wasted', 'product_id');

        $allProductIds = $produced->keys()->merge($sold->keys())->merge($wasted->keys())->unique();

        $rows = Product::whereIn('id', $allProductIds)->get()->map(function ($p) use ($produced, $sold, $wasted) {
            $prod = $produced->get($p->id, 0);
            $s = $sold->get($p->id, 0);
            $eff = $prod > 0 ? round(($s / $prod) * 100, 1) : 0;
            return [
                $p->name,
                $p->category->name ?? '—',
                $prod,
                $s,
                $wasted->get($p->id, 0),
                "{$eff}%",
            ];
        })->sortByDesc(fn ($r) => $r[3])->values();

        return $this->exportCsv(
            ['Producto', 'Categoria', 'Producido', 'Vendido', 'Desperdiciado', 'Eficiencia %'],
            $rows,
            "produccion_vs_venta_{$from}_{$to}.csv"
        );
    }
}
