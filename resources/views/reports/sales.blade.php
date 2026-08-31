@extends('layouts.app')

@section('title', 'Reporte de Ventas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="card-title mb-0">Reporte de Ventas</h2>
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Volver</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="from" value="{{ $from }}">
    <input type="hidden" name="to" value="{{ $to }}">
    <div class="col-6 col-sm-3">
        <input type="date" name="from" class="form-control" value="{{ $from }}">
    </div>
    <div class="col-6 col-sm-3">
        <input type="date" name="to" class="form-control" value="{{ $to }}">
    </div>
    <div class="col-12 col-sm-2">
        <button type="submit" class="btn btn-outline-brand w-100">Filtrar</button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Total Ingresos</p>
                <strong class="kpi-value">Bs {{ number_format($totalRevenue, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Tickets</p>
                <strong class="kpi-value">{{ $totalTickets }}</strong>
            </div>
        </div>
    </div>
    <div class="col-4">
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
                <h5 class="card-title mb-3">Por Método de Pago</h5>
                @php($methodLabels = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transferencia', 'pago_movil' => 'Pago Móvil', 'pdv' => 'PDV', 'credito' => 'Crédito'])
                @php($methodData = collect($methodLabels)->map(fn($l, $k) => $byMethod->get($k, 0))->values()->all())
                @php($chartMethods = json_encode(["type" => "bar", "indexAxis" => "y", "labels" => array_values($methodLabels), "data" => $methodData], JSON_UNESCAPED_UNICODE))
                <div style="position:relative; height:{{ count($methodLabels) * 36 + 12 }}px;">
                    <canvas data-chart='{!! $chartMethods !!}'></canvas>
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
                    <tr><th>#</th><th>Fecha</th><th>Items</th><th class="text-end">Total</th><th>Método</th></tr>
                </thead>
                <tbody>
                    @forelse($sales as $s)
                    <tr>
                        <td class="num">#{{ $s->id }}</td>
                        <td class="text-muted">{{ $s->created_at->format('d/m/Y h:i a') }}</td>
                        <td>{{ $s->items->count() }}</td>
                        <td class="text-end num">Bs {{ number_format($s->total, 2, ',', '.') }}</td>
                        @php($methods = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transfer.', 'pago_movil' => 'Pago Móvil', 'pdv' => 'PDV', 'credito' => 'Crédito'])
                        <td><span class="badge-soft {{ $s->payment_method === 'credito' ? 'warning' : 'muted' }}">{{ $methods[$s->payment_method] ?? ($methods[$s->payments->first()?->method] ?? '—') }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Sin ventas en este período</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
