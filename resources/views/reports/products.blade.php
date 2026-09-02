@extends('layouts.app')

@section('title', 'Reporte de Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Reporte de Productos</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.products-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.products'])

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoria</th>
                        <th>Tipo</th>
                        <th class="text-end">Vendidos</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Ganancia</th>
                        <th class="text-end">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td class="text-muted">{{ $p->category->name ?? '—' }}</td>
                        <td><span class="badge-soft muted">{{ $p->control_type }}</span></td>
                        <td class="text-end num">{{ $p->total_sold }}</td>
                        <td class="text-end num">Bs {{ number_format($p->total_revenue, 2, ',', '.') }}</td>
                        <td class="text-end num {{ $p->profit > 0 ? 'text-success' : ($p->profit < 0 ? 'text-danger' : '') }}">Bs {{ number_format($p->profit, 2, ',', '.') }}</td>
                        <td class="text-end num {{ $p->stock_current <= $p->stock_min ? 'text-warning' : '' }}">
                            {{ $p->stock_current }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Sin datos para este periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
