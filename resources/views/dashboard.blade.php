@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="min-w-0">
                        <p class="kpi-label">Ventas del Día</p>
                        <h3 class="kpi-value">Bs {{ number_format($totalToday, 2, ',', '.') }}</h3>
                        <span class="kpi-trend {{ $trendPercent >= 0 ? 'up' : 'down' }}">
                            @if($trendPercent > 0)
                                <i class="bi bi-graph-up-arrow"></i> +{{ $trendPercent }}% vs ayer
                            @elseif($trendPercent < 0)
                                <i class="bi bi-graph-down-arrow"></i> {{ $trendPercent }}% vs ayer
                            @else
                                Sin cambios vs ayer
                            @endif
                        </span>
                    </div>
                    <div class="kpi-icon success"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="min-w-0">
                        <p class="kpi-label">Productos Activos</p>
                        <h3 class="kpi-value">{{ $productsActive }}</h3>
                        <span class="kpi-trend muted">en el catálogo</span>
                    </div>
                    <div class="kpi-icon info"><i class="bi bi-box-seam"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="min-w-0">
                        <p class="kpi-label">Tasa BCV</p>
                        <h3 class="kpi-value">
                            @if($latestRate)
                                Bs {{ number_format($latestRate->rate, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </h3>
                        <span class="kpi-trend muted">
                            @if($latestRate)
                                {{ $latestRate->source_label }}
                            @endif
                        </span>
                    </div>
                    <div class="kpi-icon warning"><i class="bi bi-currency-exchange"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="min-w-0">
                        <p class="kpi-label">Stock Bajo</p>
                        <h3 class="kpi-value">
                            @if($stockOut > 0)
                                <span class="text-danger">{{ $stockLow + $stockOut }}</span>
                            @elseif($stockLow > 0)
                                <span class="text-warning">{{ $stockLow }}</span>
                            @else
                                0
                            @endif
                        </h3>
                        @if($stockOut > 0)
                            <span class="kpi-trend down">{{ $stockOut }} agotados</span>
                        @elseif($stockLow > 0)
                            <span class="kpi-trend muted">productos bajo mínimo</span>
                        @else
                            <span class="kpi-trend muted">sin alertas</span>
                        @endif
                    </div>
                    <div class="kpi-icon {{ $stockOut > 0 ? 'danger' : ($stockLow > 0 ? 'warning' : 'info') }}">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('pos.index') }}" class="btn btn-brand">
        <i class="bi bi-plus-lg me-1"></i>Nueva Venta
    </a>
    @can('manage-inventory')
    <a href="{{ route('inventory.index') }}" class="btn btn-outline-brand">
        <i class="bi bi-clipboard2-data me-1"></i>Inventario
    </a>
    <a href="{{ route('productions.index') }}" class="btn btn-outline-brand">
        <i class="bi bi-tools me-1"></i>Producción
    </a>
    @endcan
</section>

<section class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="card-title">Ventas por Método de Pago</h2>
                        <span class="card-sub">Hoy · total Bs {{ number_format($totalToday, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div id="payment-methods">
                    @php($methodLabels = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transferencia', 'pago_movil' => 'Pago Móvil', 'pdv' => 'PDV', 'credito' => 'Crédito'])
                    @php($methodData = collect($methodLabels)->map(fn($l, $k) => $paymentTotals->get($k, 0))->values()->all())
                    @php($chartPayment = json_encode(["type" => "bar", "indexAxis" => "y", "labels" => array_values($methodLabels), "data" => $methodData], JSON_UNESCAPED_UNICODE))
                    <div style="position:relative; height:{{ count($methodLabels) * 36 + 12 }}px;">
                        <canvas data-chart='{!! $chartPayment !!}'></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="card-title">Ventas de la Semana</h2>
                        <span class="card-sub">Últimos 7 días</span>
                    </div>
                </div>
                @php($days = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'])
                @php($dates = collect(range(0,6))->map(fn($i) => now()->subDays(6-$i)->format('Y-m-d')))
                @php($labels = $dates->map(fn($d) => $days[\Carbon\Carbon::parse($d)->format('N') - 1])->all())
                @php($weekData = $dates->map(fn($d) => $weeklySales->get($d, 0))->all())
                @php($chartWeekly = json_encode(["type" => "bar", "labels" => $labels, "data" => $weekData], JSON_UNESCAPED_UNICODE))
                <div style="position:relative; height:140px;">
                    <canvas data-chart='{!! $chartWeekly !!}'></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="card-title">Ventas Recientes</h2>
            <a href="{{ route('sales.index') }}" class="btn btn-outline-brand btn-sm">Ver todas</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Hora</th>
                        <th>Productos</th>
                        <th>Total</th>
                        <th>Método</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSales as $sale)
                    <tr>
                        <td class="num">#{{ $sale->id }}</td>
                        <td class="text-muted">{{ $sale->created_at->format('h:i a') }}</td>
                        <td>{{ $sale->items->count() }} {{ $sale->items->count() === 1 ? 'producto' : 'productos' }}</td>
                        <td class="num">Bs {{ number_format($sale->total, 2, ',', '.') }}</td>
                        <td>
                            @php($methods = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transfer.', 'pago_movil' => 'Pago Móvil', 'pdv' => 'PDV', 'credito' => 'Crédito'])
                            <span class="badge-soft {{ $sale->payment_method === 'credito' ? 'warning' : 'muted' }}">
                                {{ $methods[$sale->payment_method] ?? ($methods[$sale->payments->first()?->method] ?? '-') }}
                            </span>
                        </td>
                        <td>
                            @if($sale->status === 'completada')
                                <span class="badge-soft success">Completada</span>
                            @else
                                <span class="badge-soft danger">Anulada</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay ventas registradas aún</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </section>
@endsection

@push('charts')
    @vite(['resources/js/charts.js'])
@endpush
