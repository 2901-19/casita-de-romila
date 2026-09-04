@extends('layouts.app')

@section('title', 'Editar Producto')

@section('content')
<div>
    <div class="card">
        <div class="card-body p-3 p-lg-4">
            <form method="POST" action="{{ isset($product) ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @if(isset($product))
                    @method('PUT')
                @endif

                <div class="row g-4">

                    {{-- Left column --}}
                    <div class="col-12 col-lg-8">
                        <div class="mb-3">
                            <label for="productName" class="form-label">Nombre del producto</label>
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="productName"
                                   name="name"
                                   placeholder="Ej. Torta de Chocolate"
                                   value="{{ old('name', $product->name ?? '') }}"
                                   maxlength="150"
                                   required>
                             @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="productImage" class="form-label">Imagen de referencia</label>
                            <input type="hidden" name="remove_image" id="removeImageFlag" value="0">
                            <div class="img-dropzone" id="imgDropzone" tabindex="0" role="button" aria-label="Subir imagen">
                                <input type="file" id="productImage" name="image" accept="image/*" class="d-none">
                                <div class="dropzone-empty {{ $product->image ? 'd-none' : '' }}" id="dropzoneEmpty">
                                    <i class="bi bi-image" aria-hidden="true"></i>
                                    <p>Arrastra una imagen o <span class="link">haz clic para subir</span></p>
                                    <small>JPG, PNG o WebP · máx. 2 MB</small>
                                </div>
                                <div class="dropzone-preview {{ $product->image ? '' : 'd-none' }}" id="dropzonePreview">
                                    <img id="previewImg" src="{{ $product->image ? asset('storage/'.$product->image) : '' }}" alt="Vista previa">
                                    <button type="button" class="btn-preview-remove" id="removeImg" aria-label="Quitar imagen">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                            @error('image')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="productCategory" class="form-label">Categoría</label>
                                <select class="form-select @error('category_id') is-invalid @enderror"
                                        id="productCategory"
                                        name="category_id">
                                    <option value="">Sin categoría</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="productType" class="form-label">Tipo de control</label>
                                <select class="form-select @error('control_type') is-invalid @enderror"
                                        id="productType"
                                        name="control_type"
                                        required>
                                    <option value="" disabled {{ old('control_type', $product->control_type ?? '') === '' ? 'selected' : '' }}>Seleccionar tipo</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type->value }}" {{ old('control_type', $product->control_type ?? '') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('control_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="stockMin" class="form-label">Stock mínimo para alertas</label>
                                <input type="number"
                                       class="form-control @error('stock_min') is-invalid @enderror"
                                       id="stockMin"
                                       name="stock_min"
                                       min="0"
                                       value="{{ old('stock_min', $product->stock_min ?? 0) }}">
                                @error('stock_min')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <fieldset class="mb-3 mt-3">
                            <legend class="form-label">Horario</legend>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="schedule"
                                           id="scheduleManana"
                                           value="manana"
                                           {{ old('schedule', $product->schedule ?? 'ambos') === 'manana' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="scheduleManana">Mañana</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="schedule"
                                           id="scheduleFinde"
                                           value="finde_noche"
                                           {{ old('schedule', $product->schedule ?? 'ambos') === 'finde_noche' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="scheduleFinde">Finde Noche</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="schedule"
                                           id="scheduleAmbos"
                                           value="ambos"
                                           {{ old('schedule', $product->schedule ?? 'ambos') === 'ambos' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="scheduleAmbos">Ambos</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    {{-- Right column: Pricing --}}
                    <div class="col-12 col-lg-4">
                        <div class="pricing-card">
                            <div class="pricing-header">
                                <i class="bi bi-cash-stack" aria-hidden="true"></i> Precios
                            </div>

                            <div class="mb-3">
                                <label for="costPrice" class="form-label">Costo</label>
                                <div class="input-group">
                                    <span class="input-group-text">USD</span>
                                    <input type="number"
                                           class="form-control @error('cost_price') is-invalid @enderror"
                                           id="costPrice"
                                           name="cost_price"
                                           min="0"
                                           step="0.01"
                                           placeholder="0.00"
                                           value="{{ old('cost_price', $product->cost_price ?? '') }}">
                                    @error('cost_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="margin" class="form-label">Margen %</label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control @error('margin_percent') is-invalid @enderror"
                                           id="margin"
                                           name="margin_percent"
                                           min="0"
                                           max="99"
                                           step="1"
                                           value="{{ old('margin_percent', $product->margin_percent ?? 0) }}">
                                    <span class="input-group-text">%</span>
                                    @error('margin_percent')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <p class="field-hint" id="marginHint">Ganancia: USD 0.00</p>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Precio de venta</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text">USD</span>
                                    <input type="number"
                                           class="form-control @error('sale_price') is-invalid @enderror"
                                           id="salePriceUSD"
                                           min="0"
                                           step="0.01"
                                           placeholder="0.00"
                                           value="{{ old('sale_price', $product->sale_price ?? '') }}">
                                    <input type="hidden" name="sale_price" id="salePriceUSDHidden" value="{{ old('sale_price', $product->sale_price ?? '') }}">
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text">Bs</span>
                                    <input type="number"
                                           class="form-control"
                                           id="salePriceBs"
                                           min="0"
                                           step="0.01"
                                           placeholder="0.00"
                                           value="">
                                </div>
                                @error('sale_price')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @if($activeRate)
                                    <p class="field-hint">
                                        <i class="bi bi-info-circle"></i> Tasa: Bs {{ number_format($activeRate->rate, 2, ',', '.') }} ({{ $activeRate->source_label }})
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="status-section">
                    <div>
                        <span class="status-label">Estado del producto</span>
                        <span class="status-sub">Desactívalo para ocultarlo del catálogo</span>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input"
                               type="checkbox"
                               role="switch"
                               id="statusToggle"
                               name="is_active"
                               value="1"
                               {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="statusToggle">Activo</label>
                    </div>
                </div>

                <div class="form-footer">
                    <a class="btn btn-outline-brand" href="{{ route('products.index') }}">Cancelar</a>
                    <button type="submit" class="btn btn-brand">
                        <i class="bi bi-check2-circle me-1"></i> Guardar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var costEl = document.getElementById('costPrice');
    var marginEl = document.getElementById('margin');
    var marginHint = document.getElementById('marginHint');
    var saleUSDEl = document.getElementById('salePriceUSD');
    var saleUSDHidden = document.getElementById('salePriceUSDHidden');
    var saleBsEl = document.getElementById('salePriceBs');
    var activeRate = {{ $activeRate ? $activeRate->rate : '1' }};

    function fmt(n) { return n.toFixed(2); }

    function calcPrecioUSD(costo, margen) {
        costo = parseFloat(costo) || 0;
        margen = parseFloat(margen) || 0;
        if (costo <= 0 || margen >= 100) return 0;
        return Math.round((costo / (1 - margen / 100)) * 100) / 100;
    }

    function calcMargin(costo, precioUSD) {
        costo = parseFloat(costo) || 0;
        precioUSD = parseFloat(precioUSD) || 0;
        if (costo <= 0 || precioUSD <= 0) return 0;
        return Math.round(((precioUSD - costo) / costo * 100) * 100) / 100;
    }

    function calcBs(precioUSD) {
        return Math.round((precioUSD * activeRate) * 100) / 100;
    }

    function syncHidden() {
        saleUSDHidden.value = saleUSDEl.value;
    }

    function updateHint(costo, finalUSD) {
        costo = parseFloat(costo) || 0;
        finalUSD = parseFloat(finalUSD) || 0;
        var ganancia = Math.max(0, finalUSD - costo);
        marginHint.textContent = 'Ganancia: USD ' + fmt(ganancia);
    }

    function recalcAll(editing) {
        var costo = parseFloat(costEl.value) || 0;
        var margen = parseFloat(marginEl.value) || 0;
        var usd = parseFloat(saleUSDEl.value) || 0;
        var bs = parseFloat(saleBsEl.value) || 0;

        if (editing === 'bs') {
            bs = parseFloat(saleBsEl.value) || 0;
            usd = activeRate > 0 ? Math.round((bs / activeRate) * 100) / 100 : 0;
            saleUSDEl.value = fmt(usd);
            margen = calcMargin(costo, usd);
            marginEl.value = fmt(margen);
        } else if (editing === 'usd') {
            usd = parseFloat(saleUSDEl.value) || 0;
            bs = calcBs(usd);
            saleBsEl.value = fmt(bs);
            margen = calcMargin(costo, usd);
            marginEl.value = fmt(margen);
        } else if (editing === 'margen') {
            margen = parseFloat(marginEl.value) || 0;
            usd = calcPrecioUSD(costo, margen);
            saleUSDEl.value = fmt(usd);
            bs = calcBs(usd);
            saleBsEl.value = fmt(bs);
        } else if (editing === 'costo') {
            usd = calcPrecioUSD(costo, margen);
            saleUSDEl.value = fmt(usd);
            bs = calcBs(usd);
            saleBsEl.value = fmt(bs);
        } else {
            usd = parseFloat(saleUSDEl.value) || 0;
            bs = calcBs(usd);
            saleBsEl.value = fmt(bs);
        }

        updateHint(costo, parseFloat(saleUSDEl.value) || 0);
        syncHidden();
    }

    costEl.addEventListener('input', function () { recalcAll('costo'); });
    marginEl.addEventListener('input', function () { recalcAll('margen'); });
    saleUSDEl.addEventListener('input', function () { recalcAll('usd'); });
    saleBsEl.addEventListener('input', function () { recalcAll('bs'); });

    var dropzone = document.getElementById('imgDropzone');
    var fileInput = document.getElementById('productImage');
    var emptyEl = document.getElementById('dropzoneEmpty');
    var previewEl = document.getElementById('dropzonePreview');
    var previewImg = document.getElementById('previewImg');
    var removeBtn = document.getElementById('removeImg');
    var removeFlag = document.getElementById('removeImageFlag');

    dropzone.addEventListener('click', function () { fileInput.click(); });
    dropzone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });
    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            emptyEl.classList.add('d-none');
            previewEl.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
        removeFlag.value = '0';
    });
    removeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        fileInput.value = '';
        previewImg.src = '';
        previewEl.classList.add('d-none');
        emptyEl.classList.remove('d-none');
        removeFlag.value = '1';
    });

    recalcAll('init');
})();
</script>
@endpush
