@extends('layouts.app')

@section('title', 'Dias con mas Ventas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Dias con mas Ventas</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.top-days-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.top-days'])

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Dia</th>
                        <th class="text-end">Tickets</th>
                        <th class="text-end">Revenue (Bs)</th>
                        <th class="text-end">Promedio/Ticket</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topDays as $i => $d)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($d['day'])->format('d/m/Y') }}</td>
                        <td class="text-end num">{{ $d['tickets'] }}</td>
                        <td class="text-end num">Bs {{ number_format($d['revenue'], 2, ',', '.') }}</td>
                        <td class="text-end num">Bs {{ number_format($d['avg'], 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Sin ventas en este periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
