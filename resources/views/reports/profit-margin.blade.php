@extends('layouts.app')

@section('title', 'Margen de Ganancia')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Margen de Ganancia por Producto</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.profit-margin-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.profit-margin'])

<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Revenue Total</p>
                <strong class="kpi-value">Bs {{ number_format($totalRevenue, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Costo Total</p>
                <strong class="kpi-value">Bs {{ number_format($totalCost, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Ganancia Neta</p>
                <strong class="kpi-value {{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">Bs {{ number_format($totalProfit, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoria</th>
                        <th class="text-end">Vendidos</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Costo</th>
                        <th class="text-end">Ganancia</th>
                        <th class="text-end">Margen %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td class="text-muted">{{ $p->category_name }}</td>
                        <td class="text-end num">{{ $p->total_sold }}</td>
                        <td class="text-end num">Bs {{ number_format($p->revenue, 2, ',', '.') }}</td>
                        <td class="text-end num">Bs {{ number_format($p->cost, 2, ',', '.') }}</td>
                        <td class="text-end num {{ $p->profit > 0 ? 'text-success' : ($p->profit < 0 ? 'text-danger' : '') }}">Bs {{ number_format($p->profit, 2, ',', '.') }}</td>
                        <td class="text-end num">{{ $p->margin_pct }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Sin ventas en este periodo</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="table-group-divider">
                        <td colspan="2"><strong>Totales</strong></td>
                        <td class="text-end num"><strong>{{ $products->sum('total_sold') }}</strong></td>
                        <td class="text-end num"><strong>Bs {{ number_format($totalRevenue, 2, ',', '.') }}</strong></td>
                        <td class="text-end num"><strong>Bs {{ number_format($totalCost, 2, ',', '.') }}</strong></td>
                        <td class="text-end num"><strong class="{{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">Bs {{ number_format($totalProfit, 2, ',', '.') }}</strong></td>
                        <td class="text-end num"><strong>{{ $totalRevenue > 0 ? number_format(($totalProfit / $totalRevenue) * 100, 1) : '0' }}%</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
