@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title">Historial de Ventas</h2>
            <a href="{{ route('pos.index') }}" class="btn btn-brand btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Nueva Venta
            </a>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-6 col-sm-2">
                <select name="status" class="form-select">
                    <option value="">Estado</option>
                    <option value="completada" {{ request('status') === 'completada' ? 'selected' : '' }}>Completadas</option>
                    <option value="pendiente" {{ request('status') === 'pendiente' ? 'selected' : '' }}>Pendientes</option>
                    <option value="anulada" {{ request('status') === 'anulada' ? 'selected' : '' }}>Anuladas</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-sm-2">
                <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-6 col-sm-2">
                <button type="submit" class="btn btn-outline-brand w-100">Filtrar</button>
            </div>
            <div class="col-12 col-sm-2">
                <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Items</th>
                        <th class="text-end">Total</th>
                        <th>Método</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                    <tr>
                        <td class="num">#{{ $sale->id }}</td>
                        <td class="text-muted">{{ $sale->created_at->format('d/m/Y h:i a') }}</td>
                        <td>{{ $sale->items->count() }} {{ $sale->items->count() === 1 ? 'producto' : 'productos' }}</td>
                        <td class="text-end num">Bs {{ number_format($sale->total, 2, ',', '.') }}</td>
                        <td>
                            @php($methods = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transfer.', 'pago_movil' => 'Pago Móvil', 'pdv' => 'PDV', 'credito' => 'Crédito'])
                            <span class="badge-soft {{ $sale->payment_method === 'credito' ? 'warning' : 'muted' }}">
                                {{ $methods[$sale->payment_method] ?? ($methods[$sale->payments->first()?->method] ?? '—') }}
                            </span>
                        </td>
                        <td>
                            @if($sale->status === 'completada')
                                <span class="badge-soft success">Completada</span>
                            @elseif($sale->status === 'pendiente')
                                <span class="badge-soft warning">Pendiente</span>
                            @else
                                <span class="badge-soft danger">Anulada</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('sales.show', $sale) }}"
                                   class="btn-icon-sm"
                                   aria-label="Ver venta #{{ $sale->id }}"
                                   title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('void-sales')
                                @if($sale->status === 'completada' || $sale->status === 'pendiente')
                                    <form method="POST" action="{{ route('sales.destroy', $sale) }}" class="d-inline"
                                          data-confirm-title="¿Anular la venta #{{ $sale->id }}?"
                                          data-confirm-text="El stock será restaurado."
                                          data-confirm-button="Sí, anular">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="cancel_reason" value="Anulada desde historial">
                                        <button type="submit"
                                                class="btn-icon-sm warn"
                                                aria-label="Anular venta #{{ $sale->id }}"
                                                title="Anular">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hay ventas registradas.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center">
            {{ $sales->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
