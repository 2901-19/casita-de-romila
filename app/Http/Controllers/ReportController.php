<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\CreditMovement;
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

    protected function currentRate(): float
    {
        return (float) (ExchangeRate::latest()->first()?->rate ?? 1);
    }

    protected function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    protected function hourOfDayExpr(string $col): string
    {
        return $this->isPgsql()
            ? "EXTRACT(HOUR FROM {$col})"
            : "CAST(strftime('%H', {$col}) AS INTEGER)";
    }

    protected function dayOfWeekExpr(string $col): string
    {
        return $this->isPgsql()
            ? "EXTRACT(DOW FROM {$col})"
            : "CAST(strftime('%w', {$col}) AS INTEGER)";
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
        $pendingCreditUsd = (float) $pendingCredit;
        $pendingCreditBs = round($pendingCreditUsd * $this->currentRate(), 2);

        $totalWaste = Merma::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('quantity');

        return view('reports.index', compact('monthRevenue', 'activeProducts', 'pendingCreditUsd', 'pendingCreditBs', 'totalWaste', 'from', 'to'));
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
        $rate = $this->currentRate();

        $products = $this->salesStats($from, $to, $rate);

        return view('reports.products', compact('products', 'from', 'to'));
    }

    public function productsExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $rate = $this->currentRate();

        $products = $this->salesStats($from, $to, $rate);

        $rows = $products->map(fn ($p) => [
            $p->name,
            $p->category_name,
            $p->control_type,
            $p->total_sold,
            number_format($p->revenue, 2, ',', '.'),
            number_format($p->profit, 2, ',', '.'),
            $p->stock_current ?? '—',
        ]);

        return $this->exportCsv(
            ['Producto', 'Categoria', 'Tipo', 'Vendidos', 'Revenue (Bs)', 'Ganancia (Bs)', 'Stock'],
            $rows,
            "productos_{$from}_{$to}.csv"
        );
    }

    protected function salesStats(string $from, string $to, float $rate)
    {
        $productStats = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->whereNotNull('product_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('sale_items.product_id,
                COALESCE(SUM(sale_items.quantity), 0) as total_sold,
                COALESCE(SUM(sale_items.subtotal), 0) as revenue,
                COALESCE(SUM(sale_items.quantity * products.cost_price), 0) as cost_usd')
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy('product_id');

        $comboStats = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->whereNotNull('combo_id')
            ->join('combos', 'sale_items.combo_id', '=', 'combos.id')
            ->selectRaw('sale_items.combo_id,
                COALESCE(SUM(sale_items.quantity), 0) as total_sold,
                COALESCE(SUM(sale_items.subtotal), 0) as revenue,
                COALESCE(SUM(sale_items.quantity * combos.sale_price), 0) as cost_usd')
            ->groupBy('sale_items.combo_id')
            ->get()
            ->keyBy('combo_id');

        $items = collect();

        Product::with('category')->orderBy('name')->get()->each(function ($p) use ($productStats, $rate, $items) {
            $stat = $productStats->get($p->id);
            $items->push((object) [
                'name' => $p->name,
                'category_name' => $p->category?->name ?? '—',
                'control_type' => $p->control_type,
                'total_sold' => (int) ($stat->total_sold ?? 0),
                'revenue' => (float) ($stat->revenue ?? 0),
                'cost' => round((float) ($stat->cost_usd ?? 0) * $rate, 2),
                'stock_current' => $p->stock_current,
                'stock_min' => $p->stock_min,
            ]);
        });

        Combo::orderBy('name')->get()->each(function ($combo) use ($comboStats, $rate, $items) {
            $stat = $comboStats->get($combo->id);
            $items->push((object) [
                'name' => $combo->name,
                'category_name' => '—',
                'control_type' => 'combo',
                'total_sold' => (int) ($stat->total_sold ?? 0),
                'revenue' => (float) ($stat->revenue ?? 0),
                'cost' => round((float) ($stat->cost_usd ?? 0) * $rate, 2),
                'stock_current' => null,
                'stock_min' => null,
            ]);
        });

        $items->each(function ($item) {
            $item->profit = round($item->revenue - $item->cost, 2);
            $item->margin_pct = $item->revenue > 0 ? round(($item->profit / $item->revenue) * 100, 1) : 0;
        });

        return $items->filter(fn ($p) => $p->total_sold > 0 || ($p->stock_current ?? 0) > 0)
            ->sortBy('name')
            ->values();
    }

    // ─── Creditos ───────────────────────────────────────────

    public function credits(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $rate = $this->currentRate();

        $customers = $this->creditStats($from, $to, $rate);

        $totalDebtUsd = round($customers->sum(fn ($c) => max(0, $c->period_net_usd)), 2);
        $totalDebtBs = round($totalDebtUsd * $rate, 2);

        return view('reports.credits', compact('customers', 'totalDebtUsd', 'totalDebtBs', 'rate', 'from', 'to'));
    }

    public function creditsExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $rate = $this->currentRate();

        $customers = $this->creditStats($from, $to, $rate);

        $rows = $customers->map(fn ($c) => [
            $c->name,
            $c->phone ?? '—',
            number_format($c->period_cargos, 2, ',', '.'),
            number_format($c->period_pagos, 2, ',', '.'),
            number_format($c->period_net_usd, 2, ',', '.'),
            number_format($c->period_net_bs, 2, ',', '.'),
            $c->period_net_usd > 0 ? 'Debe' : ($c->period_net_usd < 0 ? 'A favor' : 'Al dia'),
        ]);

        return $this->exportCsv(
            ['Cliente', 'Telefono', 'Cargos (USD)', 'Pagos (USD)', 'Neto periodo (USD)', 'Neto periodo (Bs)', 'Estado'],
            $rows,
            "creditos_{$from}_{$to}.csv"
        );
    }

    protected function creditStats(string $from, string $to, float $rate)
    {
        $stats = CreditMovement::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("customer_id,
                COALESCE(SUM(CASE WHEN type = 'cargo' THEN amount ELSE 0 END), 0) as cargos,
                COALESCE(SUM(CASE WHEN type IN ('pago', 'abono') THEN amount ELSE 0 END), 0) as pagos")
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        return Customer::with(['movements' => fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)])
            ->whereIn('id', $stats->keys())
            ->orderBy('name')
            ->get()
            ->map(function ($c) use ($stats, $rate) {
                $st = $stats->get($c->id);
                $c->period_cargos = (float) ($st->cargos ?? 0);
                $c->period_pagos = (float) ($st->pagos ?? 0);
                $c->period_net_usd = round($c->period_cargos - $c->period_pagos, 2);
                $c->period_net_bs = round($c->period_net_usd * $rate, 2);
                return $c;
            })
            ->values();
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
            ->selectRaw('product_id, reason, SUM(quantity) as total_wasted, MAX(created_at) as last_date')
            ->groupBy('product_id', 'reason')
            ->orderByDesc('total_wasted')
            ->get();

        $reasonLabels = ['vencido' => 'Vencido', 'danado' => 'Danado', 'otro' => 'Otro'];

        $rows = $waste->map(fn ($w) => [
            $w->product?->name ?? '—',
            $w->total_wasted,
            $reasonLabels[$w->reason] ?? ucfirst($w->reason ?? ''),
            $w->last_date ? \Carbon\Carbon::parse($w->last_date)->format('d/m/Y H:i') : '—',
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
        $rate = $this->currentRate();

        $products = $this->salesStats($from, $to, $rate)
            ->filter(fn ($p) => $p->total_sold > 0)
            ->sortByDesc('profit')
            ->values();

        $totalRevenue = round($products->sum('revenue'), 2);
        $totalCost = round($products->sum('cost'), 2);
        $totalProfit = round($totalRevenue - $totalCost, 2);

        return view('reports.profit-margin', compact('products', 'totalRevenue', 'totalCost', 'totalProfit', 'from', 'to'));
    }

    public function profitMarginExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $rate = $this->currentRate();

        $products = $this->salesStats($from, $to, $rate)
            ->filter(fn ($p) => $p->total_sold > 0)
            ->sortByDesc('profit')
            ->values();

        $rows = $products->map(fn ($p) => [
            $p->name,
            $p->category_name,
            $p->total_sold,
            number_format($p->revenue, 2, ',', '.'),
            number_format($p->cost, 2, ',', '.'),
            number_format($p->profit, 2, ',', '.'),
            "{$p->margin_pct}%",
        ]);

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

        $bySchedule = $this->scheduleRows($from, $to, 'manana', 'noche');

        $manana = $bySchedule->get('manana');
        $noche = $bySchedule->get('noche');

        $totalTickets = ($manana->tickets ?? 0) + ($noche->tickets ?? 0);
        $totalRevenue = ($manana->revenue ?? 0) + ($noche->revenue ?? 0);

        return view('reports.sales-by-schedule', compact('manana', 'noche', 'totalTickets', 'totalRevenue', 'from', 'to'));
    }

    public function salesByScheduleExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $bySchedule = $this->scheduleRows($from, $to, 'Manana (antes 3pm)', 'Noche (despues 3pm)');

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

    protected function scheduleRows(string $from, string $to, string $labelManana, string $labelNoche)
    {
        $hour = $this->hourOfDayExpr('sales.created_at');

        return Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("CASE WHEN {$hour} < 15 THEN :manana ELSE :noche END as schedule,
                COUNT(*) as tickets, SUM(sales.total) as revenue",
                ['manana' => $labelManana, 'noche' => $labelNoche])
            ->groupBy('schedule')
            ->get()
            ->keyBy('schedule');
    }

    // ─── Productos de lento movimiento ──────────────────────

    public function slowMovers(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $products = $this->slowMoverRows($from, $to);

        return view('reports.slow-movers', compact('products', 'from', 'to'));
    }

    public function slowMoversExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $products = $this->slowMoverRows($from, $to);

        $rows = $products->map(fn ($p) => [
            $p->name,
            $p->category->name ?? '—',
            $p->control_type,
            $p->daysSinceSale ?? 'Nunca',
            $p->stock_current,
        ]);

        return $this->exportCsv(
            ['Producto', 'Categoria', 'Tipo', 'Dias sin venta', 'Stock actual'],
            $rows,
            "lento_movimiento_{$from}_{$to}.csv"
        );
    }

    protected function slowMoverRows(string $from, string $to)
    {
        $soldInRange = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')
            ->pluck('sold', 'product_id');

        $lastSaleDates = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada'))
            ->whereNotNull('product_id')
            ->selectRaw('product_id, MAX(sale_items.created_at) as last')
            ->groupBy('product_id')
            ->pluck('last', 'product_id');

        $toDate = \Carbon\Carbon::parse($to)->endOfDay();

        return Product::with('category')
            ->active()
            ->get()
            ->map(function ($p) use ($soldInRange, $lastSaleDates, $toDate) {
                $p->sold_in_range = (int) ($soldInRange->get($p->id, 0));
                $last = $lastSaleDates->get($p->id);
                $p->lastSale = $last ? \Carbon\Carbon::parse($last) : null;
                $p->daysSinceSale = $p->lastSale ? $toDate->diffInDays($p->lastSale) : null;
                return $p;
            })
            ->filter(fn ($p) => $p->sold_in_range === 0)
            ->sortByDesc('daysSinceSale')
            ->values();
    }

    // ─── Rendimiento por dia de la semana ───────────────────

    public function weeklyPerformance(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $dayLabels = [0 => 'Lunes', 1 => 'Martes', 2 => 'Miercoles', 3 => 'Jueves', 4 => 'Viernes', 5 => 'Sabado', 6 => 'Domingo'];

        $byDay = $this->weeklyRows($from, $to, $dayLabels);

        return view('reports.weekly-performance', compact('byDay', 'from', 'to'));
    }

    public function weeklyPerformanceExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $dayLabels = [0 => 'Lunes', 1 => 'Martes', 2 => 'Miercoles', 3 => 'Jueves', 4 => 'Viernes', 5 => 'Sabado', 6 => 'Domingo'];

        $byDay = $this->weeklyRows($from, $to, $dayLabels);

        $rows = $byDay->map(fn ($d) => [
            $d->day_name,
            $d->tickets,
            number_format($d->revenue, 2, ',', '.'),
            $d->avg > 0 ? number_format($d->avg, 2, ',', '.') : '0,00',
        ]);

        return $this->exportCsv(
            ['Dia', 'Tickets', 'Revenue (Bs)', 'Promedio/Ticket (Bs)'],
            $rows,
            "rendimiento_semanal_{$from}_{$to}.csv"
        );
    }

    protected function weeklyRows(string $from, string $to, array $dayLabels)
    {
        $dow = $this->dayOfWeekExpr('sales.created_at');

        return Sale::where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw("CASE {$dow} WHEN 0 THEN 6 ELSE {$dow} - 1 END as dow,
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
    }

    // ─── Produccion vs Ventas vs Mermas ─────────────────────

    public function productionVsSales(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $comparison = $this->productionComparison($from, $to);

        return view('reports.production-vs-sales', compact('comparison', 'from', 'to'));
    }

    public function productionVsSalesExport(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $comparison = $this->productionComparison($from, $to);

        $rows = $comparison->map(fn ($p) => [
            $p->name,
            $p->category_name,
            $p->produced,
            $p->sold,
            $p->wasted,
            "{$p->efficiency}%",
        ]);

        return $this->exportCsv(
            ['Producto', 'Categoria', 'Producido', 'Vendido', 'Desperdiciado', 'Eficiencia %'],
            $rows,
            "produccion_vs_venta_{$from}_{$to}.csv"
        );
    }

    protected function productionComparison(string $from, string $to)
    {
        $produced = Production::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as produced')
            ->groupBy('product_id')
            ->pluck('produced', 'product_id');

        $wasted = Merma::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as wasted')
            ->groupBy('product_id')
            ->pluck('wasted', 'product_id');

        $sold = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')
            ->pluck('sold', 'product_id');

        $comboSold = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completada')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to))
            ->whereNotNull('combo_id')
            ->selectRaw('combo_id, SUM(quantity) as sold')
            ->groupBy('combo_id')
            ->pluck('sold', 'combo_id');

        $ids = $produced->keys()->merge($wasted->keys())->unique();
        $byId = [];

        foreach (Product::with('category')->whereIn('id', $ids)->get() as $p) {
            $byId[$p->id] = [
                'id' => $p->id,
                'name' => $p->name,
                'category_name' => $p->category?->name ?? '—',
                'produced' => (int) ($produced->get($p->id, 0)),
                'sold' => (int) ($sold->get($p->id, 0) ?? 0),
                'wasted' => (int) ($wasted->get($p->id, 0)),
            ];
        }

        foreach (Product::with('category')->whereIn('id', $sold->keys())->get() as $p) {
            if (! isset($byId[$p->id])) {
                $byId[$p->id] = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'category_name' => $p->category?->name ?? '—',
                    'produced' => 0,
                    'sold' => (int) ($sold->get($p->id, 0)),
                    'wasted' => 0,
                ];
            } else {
                $byId[$p->id]['sold'] = (int) ($sold->get($p->id, 0));
            }
        }

        foreach (Combo::whereIn('id', $comboSold->keys())->get() as $combo) {
            $byId['combo_'.$combo->id] = [
                'name' => $combo->name,
                'category_name' => '—',
                'produced' => 0,
                'sold' => (int) ($comboSold->get($combo->id, 0)),
                'wasted' => 0,
            ];
        }

        return collect($byId)
            ->map(function ($row) {
                $row['efficiency'] = $row['produced'] > 0 ? round(($row['sold'] / $row['produced']) * 100, 1) : 0;
                return (object) $row;
            })
            ->sortByDesc('sold')
            ->values();
    }
}
