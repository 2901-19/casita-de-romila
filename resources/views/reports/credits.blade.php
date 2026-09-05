@extends('layouts.app')

@section('title', 'Reporte de Creditos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Reporte de Creditos</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.credits-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.credits'])

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-3 text-center">
            <div class="col-6">
                <p class="kpi-label mb-0">Nueva deuda del periodo</p>
                <div class="d-flex flex-column">
                    <strong class="kpi-value text-danger">$ {{ number_format(abs($totalDebtUsd), 2, ',', '.') }}</strong>
                    <small class="text-muted">≈ Bs {{ number_format(abs($totalDebtBs), 2, ',', '.') }} · tasa Bs {{ number_format($rate, 2, ',', '.') }}</small>
                </div>
            </div>
            <div class="col-6">
                <p class="kpi-label mb-0">Clientes con movimiento</p>
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
                    <tr><th>Cliente</th><th>Telefono</th><th class="text-end">Cargos (USD)</th><th class="text-end">Pagos (USD)</th><th class="text-end">Neto periodo</th><th class="text-center">Estado</th></tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                    <tr>
                        <td>{{ $c->name }}</td>
                        <td class="text-muted">{{ $c->phone ?? '—' }}</td>
                        <td class="text-end num">$ {{ number_format($c->period_cargos, 2, ',', '.') }}</td>
                        <td class="text-end num">$ {{ number_format($c->period_pagos, 2, ',', '.') }}</td>
                        <td class="text-end num {{ $c->period_net_usd > 0 ? 'text-danger' : ($c->period_net_usd < 0 ? 'text-success' : '') }}">
                            <span class="d-block">$ {{ number_format($c->period_net_usd, 2, ',', '.') }}</span>
                            <small class="text-muted">≈ Bs {{ number_format($c->period_net_bs, 2, ',', '.') }}</small>
                        </td>
                        <td class="text-center">
                            @if($c->period_net_usd > 0)
                                <span class="badge-soft danger">Debe</span>
                            @elseif($c->period_net_usd < 0)
                                <span class="badge-soft success">A favor</span>
                            @else
                                <span class="badge-soft muted">Al dia</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Sin movimientos en el periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection