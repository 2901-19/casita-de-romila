@extends('layouts.app')

@section('title', 'Reporte de Ventas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Reporte de Ventas</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.sales-export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> CSV</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@include('reports._date-filter', ['from' => $from, 'to' => $to, 'route' => 'reports.sales'])

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Ingresos</p>
                <strong class="kpi-value">Bs {{ number_format($totalRevenue, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total USD</p>
                <strong class="kpi-value">$ {{ number_format($totalUsd, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Tickets</p>
                <strong class="kpi-value">{{ $totalTickets }}</strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Promedio/Ticket</p>
                <strong class="kpi-value">Bs {{ number_format($avgTicket, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Por Metodo de Pago</h5>
                @php($methodLabels = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transferencia', 'pago_movil' => 'Pago Movil', 'pdv' => 'PDV', 'credito' => 'Credito'])
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Metodo</th><th class="text-end">Monto (Bs)</th><th class="text-end">% del Total</th></tr>
                        </thead>
                        <tbody>
                            @foreach($methodLabels as $key => $label)
                                @if($byMethod->get($key, 0) > 0)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-end num">Bs {{ number_format($byMethod->get($key, 0), 2, ',', '.') }}</td>
                                    <td class="text-end num">{{ $totalRevenue > 0 ? number_format(($byMethod->get($key, 0) / $totalRevenue) * 100, 1) : '0' }}%</td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Detalle de Ventas</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>#</th><th>Fecha</th><th>Items</th><th class="text-end">Total</th><th>Metodo</th></tr>
                </thead>
                <tbody>
                    @forelse($sales as $s)
                    <tr>
                        <td class="num">#{{ $s->id }}</td>
                        <td class="text-muted">{{ $s->created_at->format('d/m/Y h:i a') }}</td>
                        <td>{{ $s->items->count() }}</td>
                        <td class="text-end num">Bs {{ number_format($s->total, 2, ',', '.') }}</td>
                        @php($methods = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transfer.', 'pago_movil' => 'Pago Movil', 'pdv' => 'PDV', 'credito' => 'Credito'])
                        <td><span class="badge-soft {{ $s->payment_method === 'credito' ? 'warning' : 'muted' }}">{{ $methods[$s->payment_method] ?? ($methods[$s->payments->first()?->method] ?? '—') }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Sin ventas en este periodo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sales->links() }}
    </div>
</div>
@endsection
