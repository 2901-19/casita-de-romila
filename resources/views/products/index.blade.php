@extends('layouts.app')

@section('title', 'Productos')

@section('topbar-actions')
@can('manage-products')
<a class="btn btn-brand" href="{{ route('products.create') }}">
    <i class="bi bi-plus-lg me-1"></i>Nuevo Producto
</a>
@endcan
@endsection

@section('content')
@can('manage-products')
<div class="bulk-bar mb-3 d-none" id="bulkBar">
    <span class="bulk-label">
        <span id="bulkCount">0</span> producto<span id="bulkPlural">s</span> seleccionado<span id="bulkPlural2">s</span>
    </span>
    <span class="ms-auto d-flex flex-wrap gap-2">
        <button class="btn btn-outline-brand btn-sm" type="button" id="bulkActivate">Activar</button>
        <button class="btn btn-sm" style="border-color:var(--border);color:var(--fg);" type="button" id="bulkDeactivate">Desactivar</button>
        <button class="btn btn-sm" style="border-color:var(--danger);color:var(--danger);" type="button" id="bulkDelete">Eliminar</button>
    </span>
</div>
@endcan

<div class="card">
    <div class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-center" style="border-color: var(--border) !important;">

        <div class="search-box">
            <i class="bi bi-search search-icon" aria-hidden="true"></i>
            <form method="GET" action="{{ route('products.index') }}" id="searchForm">
                <input type="search" class="form-control" name="search" placeholder="Buscar producto..." value="{{ request('search') }}" aria-label="Buscar producto">
                @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            </form>
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="d-flex gap-2 flex-wrap align-items-center" id="filterForm">
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <input type="hidden" name="category" id="filterCategory" value="{{ request('category', 'all') }}">
            <input type="hidden" name="status" id="filterStatus" value="{{ request('status', 'all') }}">

            <div class="dropdown">
                <button class="btn btn-filter" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    <span id="categoryLabel">{{ request('category') && request('category') !== 'all' ? $categories->find(request('category'))?->name : 'Categoría' }}</span>
                    <i class="bi bi-chevron-down chev" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu" id="categoryMenu">
                    <li><button class="dropdown-item" type="button" data-value="all">Todas las categorías</button></li>
                    @foreach($categories as $cat)
                        <li><button class="dropdown-item" type="button" data-value="{{ $cat->id }}">{{ $cat->name }}</button></li>
                    @endforeach
                </ul>
            </div>

            <div class="dropdown">
                <button class="btn btn-filter" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-toggle-on" aria-hidden="true"></i>
                    <span id="statusLabel">{{ request('status') === 'activo' ? 'Activos' : (request('status') === 'inactivo' ? 'Inactivos' : 'Estado') }}</span>
                    <i class="bi bi-chevron-down chev" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu" id="statusMenu">
                    <li><button class="dropdown-item" type="button" data-value="all">Todos</button></li>
                    <li><button class="dropdown-item" type="button" data-value="activo">Activos</button></li>
                    <li><button class="dropdown-item" type="button" data-value="inactivo">Inactivos</button></li>
                </ul>
            </div>
        </form>
    </div>

    <div id="tableWrap">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        @can('manage-products')
                        <th scope="col" style="width:36px">
                            <input class="form-check-input" type="checkbox" id="selectAll" aria-label="Seleccionar todo">
                        </th>
                        @endcan
                        <th scope="col">Nombre</th>
                        <th scope="col">Stock actual</th>
                        <th scope="col">Precio Venta</th>
                        @can('manage-products')
                        <th scope="col" class="text-end">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    @foreach($products as $product)
                    <tr class="product-row"
                        data-id="{{ $product->id }}"
                        data-name="{{ $product->name }}"
                        data-category="{{ $product->category?->name }}"
                        data-status="{{ $product->is_active ? 'activo' : 'inactivo' }}"
                        data-type="{{ $product->control_type }}"
                        data-schedule="{{ $product->schedule ?? 'ambos' }}"
                        data-image="{{ $product->image ? asset('storage/'.$product->image) : '' }}"
                        data-price="{{ number_format($product->sale_price * $rate, 2, '.', '') }}"
                        data-stock="{{ $product->stock_current ?? 0 }}"
                        data-stock-min="{{ $product->stock_min ?? 0 }}">
                        @can('manage-products')
                        <td>
                            <input class="form-check-input row-check" type="checkbox" aria-label="Seleccionar">
                        </td>
                        @endcan
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="thumb" role="button" aria-label="Vista previa de {{ $product->name }}">
                                    @if($product->image)
                                        <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" loading="lazy">
                                    @else
                                        <i class="bi bi-image"></i>
                                    @endif
                                </span>
                                <div>
                                    <span class="fw-semibold">{{ $product->name }}</span>
                                    @if($product->description)
                                        <span class="d-block text-muted" style="font-size: 0.78rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $product->description }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($product->control_type !== 'demanda')
                                @php $isLow = ($product->stock_current ?? 0) <= ($product->stock_min ?? 0); @endphp
                                <span class="{{ $isLow ? 'stock-low' : 'stock-ok' }}">
                                    {{ $product->stock_current ?? 0 }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="fw-semibold">
                            Bs {{ number_format($product->sale_price * $rate, 2, ',', '.') }}
                        </td>
                        @can('manage-products')
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('products.edit', $product) }}"
                                   class="btn-icon-sm"
                                   aria-label="Editar producto {{ $product->name }}"
                                   title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <form action="{{ route('products.toggle-active', $product) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    @if($product->is_active)
                                        <button type="submit"
                                                class="btn-icon-sm act"
                                                aria-label="Desactivar producto {{ $product->name }}"
                                                title="Desactivar">
                                            <i class="bi bi-toggle-on"></i>
                                        </button>
                                    @else
                                        <button type="submit"
                                                class="btn-icon-sm warn"
                                                aria-label="Activar producto {{ $product->name }}"
                                                title="Activar">
                                            <i class="bi bi-toggle-off"></i>
                                        </button>
                                    @endif
                                </form>

                                <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline" data-confirm-title="¿Eliminar el producto {{ $product->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-icon-sm warn"
                                            aria-label="Eliminar producto {{ $product->name }}"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
                @if($products->isEmpty())
                <tbody>
                    <tr>
                        <td colspan="{{ auth()->user()->can('manage-products') ? 5 : 3 }}" class="text-center text-muted py-5">
                            @if(request()->filled('search') || (request('category') && request('category') !== 'all') || (request('status') && request('status') !== 'all'))
                                <i class="bi bi-search d-block" style="font-size:2rem;margin-bottom:0.5rem;opacity:0.4;"></i>
                                No se encontraron productos
                                <a class="btn btn-outline-brand btn-sm mt-2" href="{{ route('products.index') }}">Limpiar filtros</a>
                            @else
                                <i class="bi bi-box-seam d-block" style="font-size:2rem;margin-bottom:0.5rem;opacity:0.4;"></i>
                                No hay productos registrados
                            @endif
                        </td>
                    </tr>
                </tbody>
                @endif
            </table>
        </div>
    </div>

    @if($products->hasPages())
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top p-3" style="border-color: var(--border) !important;">
        <span class="text-muted" style="font-size: 0.84rem;">
            Mostrando {{ $products->firstItem() }}-{{ $products->lastItem() }} de {{ $products->total() }}
        </span>
        <nav aria-label="Paginación">
            {{ $products->links('pagination::bootstrap-5') }}
        </nav>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.product-row'));
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.row-check'));
    var selectAll = document.getElementById('selectAll');
    var bulkBar = document.getElementById('bulkBar');
    var bulkCount = document.getElementById('bulkCount');

    var CATEGORY_ICONS = {
        'Tortas': 'bi-cake2',
        'Bebidas': 'bi-cup-straw',
        'Fast Food': 'bi-egg-fried',
        'Salados': 'bi-basket',
        'Postres': 'bi-cookie'
    };

    function updateBulk() {
        var checked = rowChecks.filter(function (c) { return c.checked; });
        var count = checked.length;
        bulkCount.textContent = String(count);
        var pl = count === 1 ? '' : 's';
        var pl2 = count === 1 ? 'o' : 'os';
        document.getElementById('bulkPlural').textContent = pl;
        document.getElementById('bulkPlural2').textContent = pl2;
        bulkBar.classList.toggle('d-none', count === 0);
        selectAll.checked = rows.length > 0 && rows.every(function (r) { return r.querySelector('.row-check').checked; });
        selectAll.indeterminate = !selectAll.checked && checked.length > 0;
    }

    function getSelectedIds() {
        return rowChecks
            .filter(function (c) { return c.checked; })
            .map(function (c) { return parseInt(c.closest('.product-row').getAttribute('data-id'), 10); });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rows.forEach(function (r) {
                r.querySelector('.row-check').checked = selectAll.checked;
            });
            updateBulk();
        });
    }

    rowChecks.forEach(function (c) { c.addEventListener('change', updateBulk); });

    var searchForm = document.getElementById('searchForm');
    var filterForm = document.getElementById('filterForm');
    var searchInput = searchForm.querySelector('input[name="search"]');
    var initialSearch = @json(request('search', ''));
    var searchTimeout;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
            if (searchInput.value.trim() === initialSearch.trim()) return;
            searchForm.submit();
        }, 400);
    });

    document.querySelectorAll('#categoryMenu .dropdown-item').forEach(function (item) {
        item.addEventListener('click', function () {
            document.getElementById('filterCategory').value = this.getAttribute('data-value');
            filterForm.submit();
        });
    });

    document.querySelectorAll('#statusMenu .dropdown-item').forEach(function (item) {
        item.addEventListener('click', function () {
            document.getElementById('filterStatus').value = this.getAttribute('data-value');
            filterForm.submit();
        });
    });

    var bulkActivate = document.getElementById('bulkActivate');
    var bulkDeactivate = document.getElementById('bulkDeactivate');
    var bulkDelete = document.getElementById('bulkDelete');

    if (bulkActivate) {
        bulkActivate.addEventListener('click', function () {
            var ids = getSelectedIds();
            if (!ids.length) return;
            fetch('{{ route('products.bulk-toggle') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'json',
                },
                body: JSON.stringify({ ids: ids, action: 'activate' }),
            }).then(function (res) {
                if (!res.ok) throw new Error();
                window.location.reload();
            }).catch(function () {
                window.toast.fire({ icon: 'error', title: 'Error al activar productos.' });
            });
        });
    }

    if (bulkDeactivate) {
        bulkDeactivate.addEventListener('click', function () {
            var ids = getSelectedIds();
            if (!ids.length) return;
            fetch('{{ route('products.bulk-toggle') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'json',
                },
                body: JSON.stringify({ ids: ids, action: 'deactivate' }),
            }).then(function (res) {
                if (!res.ok) throw new Error();
                window.location.reload();
            }).catch(function () {
                window.toast.fire({ icon: 'error', title: 'Error al desactivar productos.' });
            });
        });
    }

    if (bulkDelete) {
        bulkDelete.addEventListener('click', function () {
            var ids = getSelectedIds();
            if (!ids.length) return;
            window.Swal.fire({
                title: '¿Eliminar ' + ids.length + ' producto(s)?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--danger)',
                reverseButtons: true,
            }).then(function (result) {
                if (!result.isConfirmed) return;
                fetch('{{ route('products.bulk-delete') }}', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'json',
                    },
                    body: JSON.stringify({ ids: ids }),
                }).then(function (res) {
                    if (!res.ok) throw new Error();
                    window.location.reload();
                }).catch(function () {
                    window.toast.fire({ icon: 'error', title: 'Error al eliminar productos.' });
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        rows.forEach(function (row) {
            var thumb = row.querySelector('.thumb');
            if (!thumb) return;
            var name = row.getAttribute('data-name') || '';
            var cat = row.getAttribute('data-category') || '';
            var icon = CATEGORY_ICONS[cat] || 'bi-image';
            var price = row.querySelector('td.fw-semibold') ? row.querySelector('td.fw-semibold').textContent.trim() : '';
            var stock = row.querySelector('.stock-low, .stock-ok') ? row.querySelector('.stock-low, .stock-ok').textContent.trim() : '—';
            var type = row.getAttribute('data-type') || '—';
            var schedule = row.getAttribute('data-schedule') || 'ambos';
            var image = row.getAttribute('data-image') || '';
            var scheduleLabels = { manana: 'Mañana', finde_noche: 'Finde Noche', ambos: 'Ambos' };
            var imgHtml = image
                ? '<img src="' + image + '" alt="">'
                : '<i class="bi ' + icon + '"></i>';
            var content =
                '<div class="product-preview">' +
                    '<div class="pp-img">' + imgHtml + '</div>' +
                    '<div class="pp-body">' +
                        '<div class="pp-name">' + name + '</div>' +
                        '<div class="pp-cat">' + (cat || 'Sin categoría') + '</div>' +
                        '<div class="pp-row"><span>Precio</span><strong>' + price + '</strong></div>' +
                        '<div class="pp-row"><span>Stock</span><strong class="' + (row.querySelector('.stock-low') ? 'stock-low' : 'stock-ok') + '">' + stock + '</strong></div>' +
                        '<div class="pp-row"><span>Tipo</span><strong>' + type.charAt(0).toUpperCase() + type.slice(1) + '</strong></div>' +
                        '<div class="pp-row"><span>Horario</span><strong>' + (scheduleLabels[schedule] || schedule) + '</strong></div>' +
                    '</div>' +
                '</div>';
            new bootstrap.Popover(thumb, {
                html: true,
                trigger: 'hover',
                placement: 'right',
                container: 'body',
                customClass: 'product-popover',
                content: content
            });
        });
    });
})();
</script>
@endpush
