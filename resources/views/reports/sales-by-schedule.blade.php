@extends('layouts.app')

@section('title', 'Ventas por Horario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Ventas por Horario</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.sales-by-schedule-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.sales-by-schedule'])

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Revenue</p>
                <strong class="kpi-value">Bs {{ number_format($totalRevenue, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Tickets</p>
                <strong class="kpi-value">{{ $totalTickets }}</strong>
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
                        <th>Horario</th>
                        <th class="text-end">Tickets</th>
                        <th class="text-end">Revenue (Bs)</th>
                        <th class="text-end">Promedio/Ticket</th>
                        <th class="text-end">% del Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-sun me-1"></i> Manana (antes 3pm)</td>
                        <td class="text-end num">{{ $manana->tickets ?? 0 }}</td>
                        <td class="text-end num">Bs {{ number_format($manana->revenue ?? 0, 2, ',', '.') }}</td>
                        <td class="text-end num">Bs {{ ($manana->tickets ?? 0) > 0 ? number_format(($manana->revenue ?? 0) / $manana->tickets, 2, ',', '.') : '0,00' }}</td>
                        <td class="text-end num">{{ $totalRevenue > 0 ? number_format((($manana->revenue ?? 0) / $totalRevenue) * 100, 1) : '0' }}%</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-moon me-1"></i> Noche (despues 3pm)</td>
                        <td class="text-end num">{{ $noche->tickets ?? 0 }}</td>
                        <td class="text-end num">Bs {{ number_format($noche->revenue ?? 0, 2, ',', '.') }}</td>
                        <td class="text-end num">Bs {{ ($noche->tickets ?? 0) > 0 ? number_format(($noche->revenue ?? 0) / $noche->tickets, 2, ',', '.') : '0,00' }}</td>
                        <td class="text-end num">{{ $totalRevenue > 0 ? number_format((($noche->revenue ?? 0) / $totalRevenue) * 100, 1) : '0' }}%</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="table-group-divider">
                        <td><strong>Total</strong></td>
                        <td class="text-end num"><strong>{{ $totalTickets }}</strong></td>
                        <td class="text-end num"><strong>Bs {{ number_format($totalRevenue, 2, ',', '.') }}</strong></td>
                        <td class="text-end num"><strong>Bs {{ $totalTickets > 0 ? number_format($totalRevenue / $totalTickets, 2, ',', '.') : '0,00' }}</strong></td>
                        <td class="text-end num"><strong>100%</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
