@extends('layouts.app')

@section('title', 'Reporte de Créditos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Reporte de Créditos</h2>
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-3 text-center">
            <div class="col-6">
                <p class="kpi-label mb-0">Total Deuda Clientes</p>
                <strong class="kpi-value text-danger">Bs {{ number_format(abs($totalDebt), 2, ',', '.') }}</strong>
            </div>
            <div class="col-6">
                <p class="kpi-label mb-0">Clientes</p>
                <strong class="kpi-value">{{ $customers->count() }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>Cliente</th><th>Teléfono</th><th class="text-end">Saldo</th><th class="text-center">Estado</th></tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                    <tr>
                        <td>{{ $c->name }}</td>
                        <td class="text-muted">{{ $c->phone ?? '—' }}</td>
                        <td class="text-end num {{ $c->balance < 0 ? 'text-danger' : ($c->balance > 0 ? 'text-success' : '') }}">
                            Bs {{ number_format($c->balance, 2, ',', '.') }}
                        </td>
                        <td class="text-center">
                            @if($c->balance < 0)
                                <span class="badge-soft danger">Debe</span>
                            @elseif($c->balance > 0)
                                <span class="badge-soft success">A favor</span>
                            @else
                                <span class="badge-soft muted">Al día</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Sin datos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
