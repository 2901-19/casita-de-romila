@extends('layouts.app')

@section('title', 'Produccion vs Ventas vs Mermas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Produccion vs Ventas vs Mermas</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.production-vs-sales-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.production-vs-sales'])

<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Producido</p>
                <strong class="kpi-value">{{ $comparison->sum('produced') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Vendido</p>
                <strong class="kpi-value">{{ $comparison->sum('sold') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Desperdiciado</p>
                <strong class="kpi-value text-danger">{{ $comparison->sum('wasted') }}</strong>
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
                        <th class="text-end">Producido</th>
                        <th class="text-end">Vendido</th>
                        <th class="text-end">Desperdiciado</th>
                        <th class="text-end">Eficiencia %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comparison as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td class="text-muted">{{ $p->category->name ?? '—' }}</td>
                        <td class="text-end num">{{ $p->produced }}</td>
                        <td class="text-end num">{{ $p->sold }}</td>
                        <td class="text-end num {{ $p->wasted > 0 ? 'text-danger' : '' }}">{{ $p->wasted }}</td>
                        <td class="text-end num {{ $p->efficiency >= 80 ? 'text-success' : ($p->efficiency >= 50 ? 'text-warning' : 'text-danger') }}">{{ $p->efficiency }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Sin datos de produccion en este periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
