@extends('layouts.app')

@section('title', 'Nuevo Combo')

@section('content')
<div>
    <div class="card">
        <div class="card-body p-3 p-lg-4">
            <form method="POST" action="{{ route('combos.store') }}" novalidate>
                @csrf

                <div class="row g-4">
                    <div class="col-12 col-lg-6">
                        <div class="mb-3">
                            <label for="comboName" class="form-label">Nombre del combo</label>
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="comboName"
                                   name="name"
                                   placeholder="Ej. Combo Desayuno"
                                   value="{{ old('name') }}"
                                   maxlength="150"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Productos del combo</label>
                            <div class="border rounded p-3 form-zone">

                                <div class="mb-3">
                                    <select id="productSelect" class="form-select">
                                        <option value="">Seleccione un producto...</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}"
                                                    data-name="{{ $product->name }}"
                                                    data-price="{{ number_format($product->sale_price, 2, '.', '') }}"
                                                    data-category="{{ $product->category->name ?? 'Sin categoría' }}">
                                                {{ $product->name }} — USD {{ number_format($product->sale_price, 2, ',', '.') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0" id="comboProductsTable">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Categoría</th>
                                                <th style="width:100px;">Cantidad</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="comboProductsBody">
                                        </tbody>
                                    </table>
                                </div>

                                @error('products')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                                @error('products.*.id')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                                @error('products.*.quantity')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card bg-board border-0">
                            <div class="card-body">
                                <h6 class="text-muted mb-3">
                                    <i class="bi bi-tag me-1"></i>Precio de Venta
                                </h6>

                                <div class="mb-3">
                                    <label for="salePriceUSD" class="form-label">Precio de venta</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="input-group">
                                                <span class="input-group-text">USD</span>
                                                <input type="number"
                                                       class="form-control @error('sale_price') is-invalid @enderror"
                                                       id="salePriceUSD"
                                                       name="sale_price"
                                                       min="0"
                                                       step="0.01"
                                                       placeholder="0.00"
                                                       value="{{ old('sale_price') }}"
                                                       required>
                                                @error('sale_price')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group">
                                                <span class="input-group-text">Bs</span>
                                                <input type="number"
                                                       class="form-control"
                                                       id="salePriceBs"
                                                       min="0"
                                                       step="0.01"
                                                       placeholder="0.00"
                                                       readonly>
                                            </div>
                                        </div>
                                    </div>
                                    @if($rate)
                                        <p class="field-hint" style="font-size:0.72rem; margin-top:0.3rem;">
                                            <i class="bi bi-info-circle"></i> Tasa: Bs {{ number_format($rate, 2, ',', '.') }}
                                        </p>
                                    @endif
                                </div>

                                <div class="mb-3" id="comboPriceSummary" style="display:none;">
                                    <div class="border rounded p-2" style="background: var(--bg);">
                                        <small class="text-muted d-block mb-2">Precio por producto</small>
                                        <div id="comboComponentsList"></div>
                                        <hr style="border-color: var(--border);">
                                        <div class="d-flex justify-content-between fw-semibold">
                                            <span>Total componentes:</span>
                                            <span id="comboTotalPrice">USD 0.00</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-brand btn-sm w-100 mt-2" id="applyComboPrice">
                                        <i class="bi bi-check2 me-1"></i>Usar este precio
                                    </button>
                                </div>

                                <hr style="border-color: var(--border);">

                                <div class="mb-3">
                                    <label for="isActive" class="form-label">Estado</label>
                                    <select class="form-select" id="isActive" name="is_active">
                                        <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Activo</option>
                                        <option value="0" {{ old('is_active', '1') == '0' ? 'selected' : '' }}>Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-brand">
                        <i class="bi bi-check2-circle me-1"></i>Guardar Combo
                    </button>
                    <a href="{{ route('combos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var productSelect = document.getElementById('productSelect');
    var comboProductsBody = document.getElementById('comboProductsBody');
    var selectedProducts = {};
    var rate = {{ $rate }};
    var comboTotalUsd = 0;

    function formatNumber(n) {
        return n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateBsPrice() {
        var usdInput = document.getElementById('salePriceUSD');
        var bsInput = document.getElementById('salePriceBs');
        var usd = parseFloat(usdInput.value) || 0;
        var bs = usd * rate;
        bsInput.value = bs.toFixed(2);
    }

    function updatePriceSummary() {
        var summary = document.getElementById('comboPriceSummary');
        var list = document.getElementById('comboComponentsList');
        var totalEl = document.getElementById('comboTotalPrice');

        if (Object.keys(selectedProducts).length === 0) {
            summary.style.display = 'none';
            return;
        }

        summary.style.display = 'block';
        var total = 0;
        list.innerHTML = '';

        Object.keys(selectedProducts).forEach(function (id) {
            var p = selectedProducts[id];
            var subtotal = p.price * p.quantity;
            total += subtotal;
            list.innerHTML += '<div class="d-flex justify-content-between small mb-1">' +
                '<span>' + p.name + ' x' + p.quantity + '</span>' +
                '<span>' +
                    '<span class="me-2">USD ' + formatNumber(subtotal) + '</span>' +
                    '<span class="text-muted">Bs ' + formatNumber(subtotal * rate) + '</span>' +
                '</span>' +
            '</div>';
        });

        comboTotalUsd = total;
        totalEl.textContent = 'USD ' + formatNumber(total) + ' — Bs ' + formatNumber(total * rate);
    }

    function renderRows() {
        comboProductsBody.innerHTML = '';
        Object.keys(selectedProducts).forEach(function (id) {
            var product = selectedProducts[id];
            var tr = document.createElement('tr');
            tr.setAttribute('data-id', id);
            tr.innerHTML =
                '<td>' +
                    '<span class="fw-semibold">' + product.name + '</span>' +
                    '<input type="hidden" name="products[' + id + '][id]" value="' + id + '">' +
                '</td>' +
                '<td>' +
                    '<span class="text-muted">' + product.category + '</span>' +
                '</td>' +
                '<td>' +
                    '<input type="number" ' +
                           'class="form-control form-control-sm" ' +
                           'name="products[' + id + '][quantity]" ' +
                           'value="' + product.quantity + '" ' +
                           'min="1" ' +
                           'data-id="' + id + '">' +
                '</td>' +
                '<td>' +
                    '<button type="button" class="btn btn-outline-danger btn-sm remove-product" data-id="' + id + '" aria-label="Quitar">' +
                        '<i class="bi bi-x"></i>' +
                    '</button>' +
                '</td>';
            comboProductsBody.appendChild(tr);
        });
        updatePriceSummary();
    }

    productSelect.addEventListener('change', function () {
        var option = productSelect.options[productSelect.selectedIndex];
        if (!option.value) return;
        var id = option.value;
        if (selectedProducts[id]) {
            selectedProducts[id].quantity++;
        } else {
            selectedProducts[id] = {
                name: option.getAttribute('data-name'),
                price: parseFloat(option.getAttribute('data-price')),
                category: option.getAttribute('data-category'),
                quantity: 1
            };
        }
        renderRows();
        productSelect.value = '';
    });

    comboProductsBody.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-product');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        delete selectedProducts[id];
        renderRows();
    });

    comboProductsBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('form-control') && e.target.hasAttribute('data-id')) {
            var id = e.target.getAttribute('data-id');
            var val = parseInt(e.target.value, 10);
            if (val > 0 && selectedProducts[id]) {
                selectedProducts[id].quantity = val;
                updatePriceSummary();
            }
        }
    });

    document.getElementById('salePriceUSD').addEventListener('input', updateBsPrice);

    document.getElementById('applyComboPrice').addEventListener('click', function () {
        document.getElementById('salePriceUSD').value = comboTotalUsd.toFixed(2);
        updateBsPrice();
    });

    updateBsPrice();
})();
</script>
@endpush
