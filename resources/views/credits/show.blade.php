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
            <p class="text-muted small mb-1">
                <i class="bi bi-whatsapp me-1"></i>{{ $customer->phone ?? 'Sin teléfono' }}
            </p>
            <p class="text-muted small mb-0">
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
            <p class="kpi-value {{ $customer->balance < 0 ? 'text-danger' : ($customer->balance > 0 ? 'text-success' : '') }}">
                $ {{ number_format($customer->balance, 2, ',', '.') }}
            </p>
            <p class="text-muted small mb-0">≈ Bs {{ number_format($customer->balance * $rate, 2, ',', '.') }} · saldo en USD</p>
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
                                <th class="text-end">Deuda (Bs)</th>
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
                                    <span class="d-block text-muted small">{{ $cs->items->count() }} {{ $cs->items->count() === 1 ? 'producto' : 'productos' }}</span>
                                </td>
                                <td class="text-muted text-nowrap">{{ $cs->created_at->format('d/m/Y') }}</td>
                                <td class="text-end num {{ $cs->status === 'pendiente' ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    Bs {{ number_format(round((float) $outstandingMap[$cs->id] * $rate, 2), 2, ',', '.') }}
                                </td>
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
                                                data-bs-total="{{ number_format(round((float) $outstandingMap[$cs->id] * $rate, 2), 2, ',', '.') }}"
                                                data-bs-usd-raw="{{ number_format($outstandingMap[$cs->id], 2, '.', '') }}"
                                                data-bs-total-raw="{{ number_format(round((float) $outstandingMap[$cs->id] * $rate, 2), 2, '.', '') }}">
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
                                    <i class="bi bi-journal-x empty-row-icon"></i>
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
                    <div class="text-muted small">
                        {{ $mov->created_at->format('d/m/Y h:i a') }} · {{ $mov->user->name ?? '—' }}
                        @if($mov->rate)
                            · tasa {{ number_format($mov->rate, 2, ',', '.') }}
                        @endif
                    </div>
                    @if($mov->sale_id || $mov->notes)
                        <div class="small">
                            @if($mov->sale_id)
                                <a href="{{ route('sales.show', $mov->sale_id) }}">Venta #{{ $mov->sale_id }}</a>
                            @endif
                            {{ ($mov->sale_id && $mov->notes) ? '· ' : '' }}{{ $mov->notes }}
                        </div>
                    @endif
                </div>
                @empty
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox empty-row-icon"></i>
                    No hay movimientos registrados.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px">
        <form method="POST" id="payForm" action="" novalidate>
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payModalLabel">Cobrar Crédito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Cobrar venta <strong id="paySaleNum">#—</strong> · saldo $ <span id="payUsdAmount">—</span></p>
                    <p class="text-muted small mb-3">≈ Bs <span id="payBsAmount">—</span> · tasa Bs {{ number_format($rate, 2, ',', '.') }}</p>

                    <label class="form-label">Método de pago</label>
                    <div class="pos-payment-methods mb-3">
                        @php($payMethods = ['efectivo' => 'bi-cash', 'biopago' => 'bi-qr-code', 'pago_movil' => 'bi-phone', 'transferencia' => 'bi-bank', 'pdv' => 'bi-credit-card-2-front'])
                        @foreach($payMethods as $key => $icon)
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="{{ $key }}" {{ $loop->first ? 'checked' : '' }}>
                            <i class="bi {{ $icon }}"></i> {{ ucwords(str_replace('_', ' ', $key)) }}
                        </label>
                        @endforeach
                    </div>
                    @error('payment_method')
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @enderror

                    <label for="payAmount" class="form-label">Monto a cobrar (Bs)</label>
                    <div class="input-group mb-2">
                        <input type="number" step="0.01" min="0.01" class="form-control" id="payAmount" name="amount_bs" placeholder="0,00" required>
                        <button type="button" class="btn btn-outline-brand" id="payAllBtn" title="Cobrar el saldo completo">Cobrar todo</button>
                    </div>
                    @error('amount_bs')
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @enderror

                    <div class="rounded p-2 bg-light small" id="payPreview">
                        <span class="text-muted">Se cobrarán ≈ $</span> <strong id="previewUsd">—</strong>
                        <span class="d-block text-muted mt-1">Quedará pendiente <strong id="previewRemainingUsd">—</strong> USD</span>
                    </div>
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
    var amountInput = document.getElementById('payAmount');
    var payAllBtn = document.getElementById('payAllBtn');
    var rate = parseFloat('{{ $rate }}');

    function syncMethodSelection() {
        var radios = payModal.querySelectorAll('input[name="payment_method"]');
        radios.forEach(function (radio) {
            radio.closest('.payment-option').classList.toggle('selected', radio.checked);
        });
    }

    function fmt(n) {
        return n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function refreshPreview() {
        var usdRaw = parseFloat(payModal.dataset.usdRaw || '0');
        var bsRaw = parseFloat(payModal.dataset.bsRaw || '0');
        var value = parseFloat(amountInput.value);

        if (isNaN(value) || value <= 0) {
            document.getElementById('previewUsd').textContent = '—';
            document.getElementById('previewRemainingUsd').textContent = '—';
            return;
        }

        var usd = value / rate;
        var remaining = Math.max(0, usdRaw - usd);

        document.getElementById('previewUsd').textContent = fmt(usd);
        document.getElementById('previewRemainingUsd').textContent = fmt(remaining);

        if (value >= bsRaw || remaining <= 0) {
            document.getElementById('previewUsd').textContent = fmt(usdRaw);
            document.getElementById('previewRemainingUsd').textContent = fmt(0);
            document.querySelector('#payPreview span.text-muted').textContent = 'Se cobrará el saldo completo ≈ $';
        } else {
            document.querySelector('#payPreview span.text-muted').textContent = 'Se cobrarán ≈ $';
        }
    }

    payModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        form.action = '{{ url('credits') }}/' + btn.getAttribute('data-customer') + '/credits/' + btn.getAttribute('data-sale-id') + '/pay';
        payModal.dataset.usdRaw = btn.getAttribute('data-bs-usd-raw');
        payModal.dataset.bsRaw = btn.getAttribute('data-bs-total-raw');
        document.getElementById('paySaleNum').textContent = '#' + btn.getAttribute('data-sale-id');
        document.getElementById('payBsAmount').textContent = btn.getAttribute('data-bs-total');
        document.getElementById('payUsdAmount').textContent = btn.getAttribute('data-bs-usd');
        amountInput.value = btn.getAttribute('data-bs-total-raw');
        document.querySelector('#payPreview span.text-muted').textContent = 'Se cobrará el saldo completo ≈ $';
        refreshPreview();
    });

    amountInput.addEventListener('input', refreshPreview);

    payModal.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
        radio.addEventListener('change', syncMethodSelection);
    });

    payAllBtn.addEventListener('click', function () {
        amountInput.value = payModal.dataset.bsRaw;
        document.querySelector('#payPreview span.text-muted').textContent = 'Se cobrará el saldo completo ≈ $';
        refreshPreview();
    });

    payModal.addEventListener('shown.bs.modal', syncMethodSelection);
});
</script>
@endpush
