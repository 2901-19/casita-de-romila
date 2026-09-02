@extends('layouts.app')

@section('title', 'Rendimiento por Dia de la Semana')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Rendimiento por Dia de la Semana</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.weekly-performance-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.weekly-performance'])

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th class="text-end">Tickets</th>
                        <th class="text-end">Revenue (Bs)</th>
                        <th class="text-end">Promedio/Ticket</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($byDay as $d)
                    <tr>
                        <td>{{ $d->day_name }}</td>
                        <td class="text-end num">{{ $d->tickets }}</td>
                        <td class="text-end num">Bs {{ number_format($d->revenue, 2, ',', '.') }}</td>
                        <td class="text-end num">Bs {{ number_format($d->avg, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Sin ventas en este periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
