@extends('layouts.app')

@section('title', 'Reporte de Mermas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Reporte de Mermas</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.waste-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.waste'])

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Unidades</p>
                <strong class="kpi-value text-danger">{{ $totalWaste }}</strong>
            </div>
        </div>
    </div>
    @foreach($byReason as $r)
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">{{ ucfirst($r->reason) }}</p>
                <strong class="kpi-value">{{ $r->total }}</strong>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Detalle por Producto</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Cantidad Desperdiciada</th>
                        <th>Ultima Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($waste as $w)
                    <tr>
                        <td>{{ $w['product']?->name ?? '—' }}</td>
                        <td class="text-end num text-danger">{{ $w['total_wasted'] }}</td>
                        <td class="text-muted">{{ $w['last_date']?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">Sin mermas en este periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
