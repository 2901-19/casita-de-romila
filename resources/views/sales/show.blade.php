@extends('layouts.app')

@section('title', "Venta #{$sale->id}")

@section('content')
<div class="mb-3">
    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
    @can('void-sales')
    @if($sale->status === 'completada' || $sale->status === 'pendiente')
        <form method="POST" action="{{ route('sales.destroy', $sale) }}" class="d-inline"
              data-confirm-title="¿Anular la venta #{{ $sale->id }}?"
              data-confirm-text="El stock será restaurado."
              data-confirm-button="Sí, anular">
            @csrf
            @method('DELETE')
            <input type="hidden" name="cancel_reason" value="Anulada desde detalle">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-x-circle me-1"></i> Anular Venta
            </button>
        </form>
    @endif
    @endcan
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="card-title">Venta #{{ $sale->id }}</h2>
                        <span class="text-muted">{{ $sale->created_at->format('l, d F Y · h:i a') }}</span>
                    </div>
                    @if($sale->status === 'completada')
                        <span class="badge-soft success" style="font-size:0.9rem; padding:0.4rem 0.8rem;">Completada</span>
                    @elseif($sale->status === 'pendiente')
                        <span class="badge-soft warning" style="font-size:0.9rem; padding:0.4rem 0.8rem;">Pendiente</span>
                    @else
                        <span class="badge-soft danger" style="font-size:0.9rem; padding:0.4rem 0.8rem;">Anulada</span>
                    @endif
                </div>

                @if($sale->status === 'anulada')
                    <div class="alert alert-danger mb-3">
                        <i class="bi bi-x-circle me-1"></i>
                        <strong>Razón:</strong> {{ $sale->cancel_reason }}<br>
                        <small>Anulada por {{ $sale->canceledBy->name ?? '—' }} el {{ $sale->canceled_at->format('d/m/Y h:i a') }}</small>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->items as $item)
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td class="text-end num">{{ $item->quantity }}</td>
                                <td class="text-end num">Bs {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                <td class="text-end num">Bs {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total</strong></td>
                                <td class="text-end num"><strong>Bs {{ number_format($sale->items->sum('subtotal'), 2, ',', '.') }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title mb-3">Información</h3>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Cajero</dt>
                    <dd class="col-7">{{ $sale->user->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Fecha</dt>
                    <dd class="col-7">{{ $sale->created_at->format('d/m/Y h:i a') }}</dd>
                    <dt class="col-5 text-muted">Estado</dt>
                    <dd class="col-7">
                        @if($sale->status === 'completada')
                            <span class="badge-soft success">Completada</span>
                        @elseif($sale->status === 'pendiente')
                            <span class="badge-soft warning">Pendiente</span>
                        @else
                            <span class="badge-soft danger">Anulada</span>
                        @endif
                    </dd>
                    @if($sale->customer)
                        <dt class="col-5 text-muted">Cliente</dt>
                        <dd class="col-7">
                            @can('manage-credits')
                                <a href="{{ route('credits.show', $sale->customer) }}">{{ $sale->customer->name }}</a>
                            @else
                                {{ $sale->customer->name }}
                            @endcan
                        </dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title mb-3">Pagos</h3>
                @if($sale->payment_method === 'credito')
                    @can('manage-credits')
                    <a class="btn btn-sm btn-outline-brand"
                       href="{{ route('credits.show', $sale->customer) }}">
                        <i class="bi bi-journal-bookmark me-1"></i> Registrar pago
                    </a>
                    @else
                    <span class="badge-soft warning">Pago pendiente (crédito)</span>
                    @endcan
                @else
                    @foreach($sale->payments as $payment)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            @php($methods = ['efectivo' => 'Efectivo', 'biopago' => 'Biopago', 'transferencia' => 'Transferencia', 'pago_movil' => 'Pago Móvil', 'pdv' => 'PDV'])
                            <span class="badge-soft muted">{{ $methods[$payment->method] ?? $payment->method }}</span>
                        </div>
                        <strong>Bs {{ number_format($payment->amount, 2, ',', '.') }}</strong>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
