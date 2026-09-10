@extends('layouts.app')

@section('title', 'Mermas')

@section('topbar-actions')
@can('manage-waste')
<button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#mermaModal">
    <i class="bi bi-plus-lg me-1"></i> Registrar Salida
</button>
@endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="kpi-icon danger"><i class="bi bi-trash3"></i></div>
                    <div>
                        <p class="kpi-label mb-0">Mermas hoy</p>
                        <strong class="kpi-value">{{ $totalToday }}</strong>
                        <span class="kpi-trend muted">unidades</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="kpi-icon info"><i class="bi bi-cup-hot"></i></div>
                    <div>
                        <p class="kpi-label mb-0">Consumo interno hoy</p>
                        <strong class="kpi-value">{{ $totalConsumption }}</strong>
                        <span class="kpi-trend muted">unidades · USD {{ number_format($totalConsumptionCost, 2, ',', '.') }} en costo</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title">Registro de Mermas y Consumo</h2>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-sm-3">
                <select name="product_id" class="form-select">
                    <option value="">Todos los productos</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <select name="type" class="form-select">
                    <option value="">Tipo</option>
                    <option value="merma" {{ request('type') === 'merma' ? 'selected' : '' }}>Merma</option>
                    <option value="consumo" {{ request('type') === 'consumo' ? 'selected' : '' }}>Consumo interno</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <select name="reason" class="form-select">
                    <option value="">Razón</option>
                    <option value="vencido" {{ request('reason') === 'vencido' ? 'selected' : '' }}>Vencido</option>
                    <option value="danado" {{ request('reason') === 'danado' ? 'selected' : '' }}>Dañado</option>
                    <option value="otro" {{ request('reason') === 'otro' ? 'selected' : '' }}>Otro</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-sm-2">
                <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-6 col-sm-1">
                <button type="submit" class="btn btn-outline-brand w-100">Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Producto</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Costo USD</th>
                        <th>Razón</th>
                        <th>Notas</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mermas as $m)
                    <tr>
                        <td class="text-muted">{{ $m->created_at->format('d/m/Y h:i a') }}</td>
                        <td><span class="badge-soft {{ $m->type_badge }}">{{ $m->type_label }}</span></td>
                        <td>{{ $m->product->name }}</td>
                        <td class="text-end num text-danger">-{{ $m->quantity }}</td>
                        <td class="text-end num">
                            @if($m->isConsumption() && $m->subtotal() !== null)
                                USD {{ number_format($m->subtotal(), 2, ',', '.') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><span class="badge-soft {{ $m->isConsumption() ? 'info' : 'danger' }}">{{ $m->reason_label }}</span></td>
                        <td class="text-muted">{{ $m->notes ?? '—' }}</td>
                        <td class="text-muted">{{ $m->user->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No hay registros de salidas.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($mermas->hasPages())
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top p-3">
            <span class="text-muted small">
                Mostrando {{ $mermas->firstItem() }}-{{ $mermas->lastItem() }} de {{ $mermas->total() }}
            </span>
            <nav aria-label="Paginación">
                {{ $mermas->withQueryString()->links() }}
            </nav>
        </div>
        @endif
    </div>
</div>
@endsection

@section('modals')
@can('manage-waste')
<form method="POST" action="{{ route('mermas.store') }}" id="mermaForm">
    @csrf
    <div class="modal fade" id="mermaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mermaModalTitle"><i class="bi bi-exclamation-triangle me-1"></i> Reportar Merma</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mermaType" class="form-label">Tipo de salida *</label>
                        <select class="form-select @error('type') is-invalid @enderror" id="mermaType" name="type" required>
                            <option value="merma" {{ old('type', 'merma') === 'merma' ? 'selected' : '' }}>Merma (vencido, dañado)</option>
                            <option value="consumo" {{ old('type') === 'consumo' ? 'selected' : '' }}>Consumo interno (lo consume el dueño sin pago)</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="border rounded p-3 form-zone mb-3">
                        <label class="form-label">Productos a registrar</label>
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <select id="mermaProduct" class="form-select">
                                    <option value="">Seleccionar producto...</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}"
                                                data-name="{{ $p->name }}"
                                                data-cost="{{ number_format((float) $p->cost_price, 2, '.', '') }}">
                                            {{ $p->name }} (Stock: {{ $p->stock_current }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4 col-sm-2">
                                <input type="number" id="mermaQuantity" class="form-control" min="1" value="1" placeholder="Cant.">
                            </div>
                            <div class="col-4 col-sm-2 d-none" id="mermaCostWrap">
                                <input type="number" id="mermaCost" class="form-control" min="0" step="0.01" value="0" placeholder="Costo USD">
                            </div>
                            <div class="col-4 col-sm-2">
                                <button type="button" class="btn btn-outline-brand w-100" id="mermaAddRow">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                        @error('lines')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1" id="mermaLinesTable">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th style="width:110px;">Cantidad</th>
                                        <th style="width:130px;" class="cost-col">Costo USD</th>
                                        <th class="text-end cost-col">Subtotal</th>
                                        <th style="width:40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="mermaLinesBody"></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between small fw-semibold">
                            <span id="mermaUnitsLabel">0 unidades</span>
                            <span id="mermaTotalLabel" class="cost-col">USD 0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="mermaReason" class="form-label">Razón *</label>
                        <select class="form-select @error('reason') is-invalid @enderror" id="mermaReason" name="reason" required>
                            <option value="vencido" {{ old('reason') === 'vencido' ? 'selected' : '' }} data-type="merma">Vencido</option>
                            <option value="danado" {{ old('reason') === 'danado' ? 'selected' : '' }} data-type="merma">Dañado</option>
                            <option value="otro" {{ old('reason') === 'otro' ? 'selected' : '' }} data-type="merma">Otro</option>
                            <option value="otro" data-type="consumo">Consumo del dueño</option>
                        </select>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="mermaNotes" class="form-label">Notas</label>
                        <textarea class="form-control" id="mermaNotes" name="notes" rows="2" placeholder="Opcional">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand" id="mermaSubmit"><i class="bi bi-check2-circle me-1"></i> Registrar Merma</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endcan
@endsection

@if($errors->any())
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mermaModal')).show();
});
</script>
@endpush
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var typeEl = document.getElementById('mermaType');
    var reasonEl = document.getElementById('mermaReason');
    var titleEl = document.getElementById('mermaModalTitle');
    var submitEl = document.getElementById('mermaSubmit');
    var productSelect = document.getElementById('mermaProduct');
    var qtyInput = document.getElementById('mermaQuantity');
    var costInput = document.getElementById('mermaCost');
    var costWrap = document.getElementById('mermaCostWrap');
    var linesBody = document.getElementById('mermaLinesBody');
    var unitsLabel = document.getElementById('mermaUnitsLabel');
    var totalLabel = document.getElementById('mermaTotalLabel');
    var addBtn = document.getElementById('mermaAddRow');
    var mermaForm = document.getElementById('mermaForm');
    var rows = {};

    function isConsumption() { return typeEl.value === 'consumo'; }

    function syncCostColumn() {
        costWrap.classList.toggle('d-none', !isConsumption());
        var show = isConsumption();
        document.querySelectorAll('.cost-col').forEach(function (el) { el.classList.toggle('d-none', !show); });
    }

    function fmt(n) {
        return n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderRows() {
        linesBody.innerHTML = '';
        Object.keys(rows).forEach(function (id, i) {
            var r = rows[id];
            var subtotal = r.quantity * r.cost;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' +
                    '<span class="fw-semibold">' + r.name + '</span>' +
                    '<input type="hidden" name="lines[' + i + '][product_id]" value="' + id + '">' +
                '</td>' +
                '<td>' +
                    '<input type="number" class="form-control form-control-sm" min="1" ' +
                           'name="lines[' + i + '][quantity]" value="' + r.quantity + '" data-id="' + id + '">' +
                '</td>' +
                '<td class="cost-col">' +
                    '<input type="number" class="form-control form-control-sm" min="0" step="0.01" ' +
                           'name="lines[' + i + '][cost]" value="' + r.cost + '" data-id="' + id + '" data-cost> ' +
                '</td>' +
                '<td class="text-end num cost-col">USD ' + fmt(subtotal) + '</td>' +
                '<td>' +
                    '<button type="button" class="btn btn-outline-danger btn-sm remove-line" data-id="' + id + '" aria-label="Quitar">' +
                        '<i class="bi bi-x"></i>' +
                    '</button>' +
                '</td>';
            linesBody.appendChild(tr);
        });
        syncCostColumn();
        updateTotals();
    }

    function updateTotals() {
        var units = 0, total = 0;
        Object.keys(rows).forEach(function (id) {
            units += rows[id].quantity;
            total += rows[id].quantity * rows[id].cost;
        });
        unitsLabel.textContent = units + (units === 1 ? ' unidad' : ' unidades');
        totalLabel.textContent = 'USD ' + fmt(total);
    }

    function syncType() {
        var type = typeEl.value;
        var options = reasonEl.querySelectorAll('option[data-type]');
        options.forEach(function (opt) {
            opt.style.display = opt.getAttribute('data-type') === type ? '' : 'none';
        });
        var selected = reasonEl.options[reasonEl.selectedIndex];
        if (!selected || selected.getAttribute('data-type') !== type) {
            var first = reasonEl.querySelector('option[data-type="' + type + '"]');
            if (first) { reasonEl.value = first.value; }
        }
        if (type === 'consumo') {
            titleEl.innerHTML = '<i class="bi bi-cup-hot me-1"></i> Consumo interno (sin pago)';
            submitEl.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Registrar Consumo';
        } else {
            titleEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Reportar Merma';
            submitEl.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Registrar Merma';
        }
        renderRows();
    }

    addBtn.addEventListener('click', function () {
        var option = productSelect.options[productSelect.selectedIndex];
        if (!option.value) { productSelect.focus(); return; }
        var id = option.value;
        if (rows[id]) {
            rows[id].quantity += parseInt(qtyInput.value, 10) || 1;
        } else {
            rows[id] = {
                name: option.getAttribute('data-name'),
                cost: isConsumption() ? (parseFloat(costInput.value) || 0) : 0,
                quantity: parseInt(qtyInput.value, 10) || 1
            };
        }
        renderRows();
        productSelect.value = '';
        qtyInput.value = 1;
        costInput.value = isConsumption() ? (parseFloat(option.getAttribute('data-cost')) || 0) : 0;
    });

    linesBody.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-line');
        if (!btn) return;
        delete rows[btn.getAttribute('data-id')];
        renderRows();
    });

    linesBody.addEventListener('input', function (e) {
        var input = e.target;
        if (!input.hasAttribute('data-id')) return;
        var id = input.getAttribute('data-id');
        if (!rows[id]) return;
        if (input.hasAttribute('data-cost')) {
            rows[id].cost = parseFloat(input.value) || 0;
        } else {
            rows[id].quantity = parseInt(input.value, 10) || 0;
        }
        updateTotals();
    });

    typeEl.addEventListener('click', function () {
        if (isConsumption() && parseFloat(costInput.value) === 0) {
            costInput.value = productSelect.options[productSelect.selectedIndex]?.getAttribute('data-cost') || 0;
        }
    });

    typeEl.addEventListener('change', syncType);
    productSelect.addEventListener('change', function () {
        var option = productSelect.options[productSelect.selectedIndex];
        costInput.value = option && option.getAttribute('data-cost') ? option.getAttribute('data-cost') : 0;
    });
    qtyInput.addEventListener('change', function () {
        if ((parseInt(qtyInput.value, 10) || 0) < 1) qtyInput.value = 1;
    });

    mermaForm.addEventListener('submit', function (e) {
        if (Object.keys(rows).length === 0) {
            e.preventDefault();
            alert('Agregue al menos un producto.');
        }
    });

    syncType();
});
</script>
@endpush