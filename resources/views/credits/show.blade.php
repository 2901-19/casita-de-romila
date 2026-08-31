@extends('layouts.app')

@section('title', $customer->name)

@section('content')
<div class="mb-3">
    <a href="{{ route('credits.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Volver a Créditos
    </a>
</div>

<div class="card credit-hero mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="avatar credit-avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
        <div class="flex-grow-1 min-w-0">
            <h4 class="mb-1 text-truncate">{{ $customer->name }}</h4>
            <p class="text-muted mb-1" style="font-size:0.82rem;">
                <i class="bi bi-whatsapp me-1"></i>{{ $customer->phone ?? 'Sin teléfono' }}
            </p>
            <p class="mb-0" style="font-size:0.78rem; color:var(--muted);">
                <i class="bi bi-sliders me-1"></i>Límite:
                @if($customer->hasDefinedLimit())
                    <strong>$ {{ number_format($customer->credit_limit_amount, 2, ',', '.') }}</strong>
                    · disponible $ {{ number_format($customer->availableCredit(), 2, ',', '.') }}
                @else
                    <strong>Libre</strong>
                @endif
            </p>
        </div>
        <div class="credit-balance ms-auto text-md-end w-100 w-md-auto">
            <span class="badge-soft {{ $customer->balance < 0 ? 'danger' : ($customer->balance > 0 ? 'success' : 'muted') }} mb-2 d-inline-block">
                @if($customer->balance < 0) Adeuda @elseif($customer->balance > 0) A favor @else Al día @endif
            </span>
            <p class="kpi-value" style="color:{{ $customer->balance < 0 ? 'var(--danger)' : ($customer->balance > 0 ? 'var(--success)' : 'var(--fg)') }};">
                $ {{ number_format($customer->balance, 2, ',', '.') }}
            </p>
            <p class="text-muted mb-0" style="font-size:0.75rem;">≈ Bs {{ number_format($customer->balance * $rate, 2, ',', '.') }} · saldo en USD</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-journal-bookmark me-1"></i> Créditos del Cliente
                    </h5>
                    @if($pendingCount > 0)
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-soft danger">{{ $pendingCount }} {{ $pendingCount === 1 ? 'pendiente' : 'pendientes' }}</span>
                            <span class="badge-soft warning">$ {{ number_format($pendingUsd, 2, ',', '.') }} por cobrar</span>
                        </div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Venta</th>
                                <th>Fecha</th>
                                <th class="text-end">Total (Bs)</th>
                                <th class="text-end">Deuda (USD)</th>
                                <th>Estado</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($creditSales as $cs)
                            <tr>
                                <td>
                                    <a href="{{ route('sales.show', $cs) }}" class="text-decoration-none fw-semibold">#{{ $cs->id }}</a>
                                    <span class="d-block text-muted" style="font-size:0.72rem;">{{ $cs->items->count() }} {{ $cs->items->count() === 1 ? 'producto' : 'productos' }}</span>
                                </td>
                                <td class="text-muted text-nowrap">{{ $cs->created_at->format('d/m/Y') }}</td>
                                <td class="text-end num">Bs {{ number_format($cs->total, 2, ',', '.') }}</td>
                                <td class="text-end num {{ $cs->status === 'pendiente' ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    {{ $cs->status === 'completada' && $outstandingMap[$cs->id] <= 0 ? '$ 0,00' : ('$ ' . number_format($outstandingMap[$cs->id], 2, ',', '.')) }}
                                </td>
                                <td>
                                    @if($cs->status === 'pendiente')
                                        <span class="badge-soft danger">Pendiente</span>
                                    @elseif($cs->status === 'completada')
                                        <span class="badge-soft success">Cancelado</span>
                                    @else
                                        <span class="badge-soft muted">Anulado</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($cs->status === 'pendiente' && $outstandingMap[$cs->id] > 0)
                                        <button type="button"
                                                class="btn btn-brand btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#payModal"
                                                data-customer="{{ $customer->id }}"
                                                data-sale-id="{{ $cs->id }}"
                                                data-bs-usd="{{ number_format($outstandingMap[$cs->id], 2, ',', '.') }}"
                                                data-bs-total="{{ number_format(round((float) $outstandingMap[$cs->id] * $rate, 2), 2, ',', '.') }}">
                                            <i class="bi bi-cash-coin me-1"></i> Cobrar
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-journal-x d-block mb-2" style="font-size:1.6rem;"></i>
                                    Este cliente no tiene ventas a crédito.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="bi bi-clock-history me-1"></i> Movimientos</h5>
                @forelse($customer->movements->sortByDesc('created_at') as $mov)
                <div class="movement-row">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge-soft {{ $mov->type === 'cargo' ? 'danger' : ($mov->type === 'pago' ? 'success' : 'warning') }}">{{ $mov->type_label }}</span>
                        <strong class="num {{ $mov->type === 'cargo' ? 'text-danger' : 'text-success' }}">
                            {{ $mov->type === 'cargo' ? '-' : '+' }}$ {{ number_format($mov->amount, 2, ',', '.') }}
                        </strong>
                    </div>
                    <div class="text-muted" style="font-size:0.72rem;">
                        {{ $mov->created_at->format('d/m/Y h:i a') }} · {{ $mov->user->name ?? '—' }}
                        @if($mov->rate)
                            · tasa {{ number_format($mov->rate, 2, ',', '.') }}
                        @endif
                    </div>
                    @if($mov->sale_id || $mov->notes)
                        <div style="font-size:0.72rem; color:var(--muted);">
                            @if($mov->sale_id)
                                <a href="{{ route('sales.show', $mov->sale_id) }}">Venta #{{ $mov->sale_id }}</a>
                            @endif
                            {{ ($mov->sale_id && $mov->notes) ? '· ' : '' }}{{ $mov->notes }}
                        </div>
                    @endif
                </div>
                @empty
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox d-block mb-2" style="font-size:1.4rem;"></i>
                    No hay movimientos registrados.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" id="payForm" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payModalLabel">Cobrar Crédito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-2">Cobrar la venta <strong id="paySaleNum">#—</strong></p>
                    <p class="kpi-value mb-1" style="font-size:1.7rem;">Bs <span id="payBsAmount">—</span></p>
                    <p class="text-muted mb-0" style="font-size:0.8rem;">≈ $ <span id="payUsdAmount">—</span> USD · tasa Bs {{ number_format($rate, 2, ',', '.') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i> Confirmar cobro</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var payModal = document.getElementById('payModal');
    var form = document.getElementById('payForm');

    payModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        form.action = '{{ url('credits') }}/' + btn.getAttribute('data-customer') + '/credits/' + btn.getAttribute('data-sale-id') + '/pay';
        document.getElementById('paySaleNum').textContent = '#' + btn.getAttribute('data-sale-id');
        document.getElementById('payBsAmount').textContent = btn.getAttribute('data-bs-total');
        document.getElementById('payUsdAmount').textContent = btn.getAttribute('data-bs-usd');
    });
});
</script>
@endpush
