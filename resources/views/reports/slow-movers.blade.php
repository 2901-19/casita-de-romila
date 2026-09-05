@extends('layouts.app')

@section('title', 'Productos de Lento Movimiento')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Productos de Lento Movimiento</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.slow-movers-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.slow-movers'])

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-3 text-center">
            <div class="col-6">
                <p class="kpi-label mb-0">Sin ventas en el periodo</p>
                <strong class="kpi-value text-warning">{{ $products->count() }}</strong>
            </div>
            <div class="col-6">
                <p class="kpi-label mb-0">Periodo</p>
                <strong class="kpi-value small">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} → {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</strong>
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
                        <th>Tipo</th>
                        <th class="text-end">Dias sin venta</th>
                        <th class="text-end">Stock actual</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td class="text-muted">{{ $p->category->name ?? '—' }}</td>
                        <td><span class="badge-soft muted">{{ $p->control_type }}</span></td>
                        <td class="text-end num">{{ $p->daysSinceSale ?? 'Nunca' }}</td>
                        <td class="text-end num {{ $p->stock_current <= $p->stock_min ? 'text-warning' : '' }}">{{ $p->stock_current }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Todos los productos tuvieron ventas en el periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection