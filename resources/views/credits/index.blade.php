@extends('layouts.app')

@section('title', 'Créditos')

@section('topbar-actions')
<button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#customerModal">
    <i class="bi bi-person-plus me-1"></i> Nuevo Cliente
</button>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title">Cuentas Corrientes</h2>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-sm-4">
                <input type="search" name="search" class="form-control" placeholder="Buscar por nombre..." value="{{ request('search') }}">
            </div>
            <div class="col-6 col-sm-3">
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="deuda" {{ request('status') === 'deuda' ? 'selected' : '' }}>Con deuda</option>
                    <option value="favor" {{ request('status') === 'favor' ? 'selected' : '' }}>A favor</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <button type="submit" class="btn btn-outline-brand w-100">Filtrar</button>
            </div>
            <div class="col-12 col-sm-2">
                <a href="{{ route('credits.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Teléfono</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                    <tr>
                        <td>
                            <a href="{{ route('credits.show', $c) }}" class="text-decoration-none fw-semibold">{{ $c->name }}</a>
                        </td>
                        <td class="text-muted">{{ $c->phone ?? '—' }}</td>
                        <td class="text-end num {{ $c->balance < 0 ? 'text-danger' : ($c->balance > 0 ? 'text-success' : '') }}">
                            $ {{ number_format($c->balance, 2, ',', '.') }}
                            <span class="d-block text-muted" style="font-size: 0.72rem;">
                                Bs {{ number_format($c->balance * $rate, 2, ',', '.') }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($c->balance < 0)
                                <span class="badge-soft danger">Debe</span>
                            @elseif($c->balance > 0)
                                <span class="badge-soft success">A favor</span>
                            @else
                                <span class="badge-soft muted">Al día</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('credits.show', $c) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#editCustomerModal"
                                        data-url="{{ route('credits.update', $c) }}"
                                        data-name="{{ $c->name }}" data-phone="{{ $c->phone }}"
                                        data-limit-type="{{ $c->credit_limit_type }}"
                                        data-limit-amount="{{ $c->credit_limit_amount }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No hay clientes registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center">
            {{ $customers->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection

@section('modals')
<form method="POST" action="{{ route('credits.store') }}">
    @csrf
    <div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-1"></i> Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="custName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="custName" name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="custPhone" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="custPhone" name="phone" value="{{ old('phone') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Límite de crédito (deudas en USD)</label>
                        <div class="d-flex gap-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="credit_limit_type" id="clLibre" value="libre" {{ old('credit_limit_type', 'libre') === 'libre' ? 'checked' : '' }}>
                                <label class="form-check-label" for="clLibre">Libre</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="credit_limit_type" id="clMonto" value="monto" {{ old('credit_limit_type') === 'monto' ? 'checked' : '' }}>
                                <label class="form-check-label" for="clMonto">Monto definido</label>
                            </div>
                        </div>
                        <div id="clAmountWrap" class="@if(old('credit_limit_type', 'libre') !== 'monto') d-none @endif">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$ USD</span>
                                <input type="number" step="0.01" min="0" id="clAmount" class="form-control @error('credit_limit_amount') is-invalid @enderror" name="credit_limit_amount" value="{{ old('credit_limit_amount') }}" placeholder="0.00" @disabled(old('credit_limit_type', 'libre') !== 'monto')}>
                            </div>
                            @error('credit_limit_amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i> Crear Cliente</button>
                </div>
            </div>
        </div>
    </div>
</form>

<form method="POST" action="#" id="editCustomerForm">
    @csrf
    @method('PUT')
    <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-1"></i> Editar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPhone" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="editPhone" name="phone">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Límite de crédito (deudas en USD)</label>
                        <div class="d-flex gap-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="credit_limit_type" id="editClLibre" value="libre">
                                <label class="form-check-label" for="editClLibre">Libre</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="credit_limit_type" id="editClMonto" value="monto">
                                <label class="form-check-label" for="editClMonto">Monto definido</label>
                            </div>
                        </div>
                        <div id="editClAmountWrap" class="d-none">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$ USD</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="editClAmount" name="credit_limit_amount" placeholder="0.00" disabled>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function bindLimitToggle(libreId, montoId, wrapId, inputId) {
        var monto = document.getElementById(montoId);
        var wrap = document.getElementById(wrapId);
        var input = document.getElementById(inputId);

        function sync() {
            var isMonto = monto.checked;
            wrap.classList.toggle('d-none', !isMonto);
            input.disabled = !isMonto;
        }

        [libreId, montoId].forEach(function (id) {
            document.getElementById(id).addEventListener('change', sync);
        });

        return sync;
    }

    bindLimitToggle('clLibre', 'clMonto', 'clAmountWrap', 'clAmount');
    var syncEdit = bindLimitToggle('editClLibre', 'editClMonto', 'editClAmountWrap', 'editClAmount');

    var editModal = document.getElementById('editCustomerModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('editCustomerForm').action = btn.getAttribute('data-url');
        document.getElementById('editName').value = btn.getAttribute('data-name') || '';
        document.getElementById('editPhone').value = btn.getAttribute('data-phone') || '';
        var limitType = btn.getAttribute('data-limit-type') || 'libre';
        document.getElementById('editClLibre').checked = limitType === 'libre';
        document.getElementById('editClMonto').checked = limitType === 'monto';
        document.getElementById('editClAmount').value = btn.getAttribute('data-limit-amount') || '';
        syncEdit();
    });
});
</script>
@endpush
