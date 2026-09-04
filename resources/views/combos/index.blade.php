@extends('layouts.app')

@section('title', 'Combos')

@section('topbar-actions')
@can('manage-products')
<a class="btn btn-brand" href="{{ route('combos.create') }}">
    <i class="bi bi-plus-lg me-1"></i>Nuevo Combo
</a>
@endcan
@endsection

@section('content')
<div class="card">
    <div class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-center" style="border-color: var(--border) !important;">

        <div class="search-box">
            <i class="bi bi-search search-icon" aria-hidden="true"></i>
            <form method="GET" action="{{ route('combos.index') }}" id="searchForm">
                <input type="search" class="form-control" name="search" placeholder="Buscar combo..." value="{{ request('search') }}" aria-label="Buscar combo">
                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            </form>
        </div>

        <form method="GET" action="{{ route('combos.index') }}" class="d-flex gap-2 flex-wrap align-items-center" id="filterForm">
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <input type="hidden" name="status" id="filterStatus" value="{{ request('status', 'all') }}">

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
                        <th scope="col">Nombre</th>
                        <th scope="col">Productos</th>
                        <th scope="col">Precio Venta</th>
                        <th scope="col" class="text-center">Estado</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($combos as $combo)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="thumb">
                                    <i class="bi bi-box-seam"></i>
                                </span>
                                <span class="fw-semibold">{{ $combo->name }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="text-muted">{{ $combo->products->count() }} producto(s)</span>
                        </td>
                        <td class="fw-semibold">
                            Bs {{ number_format($combo->sale_price * $rate, 2, ',', '.') }}
                        </td>
                        <td class="text-center">
                            @if($combo->is_active)
                                <span class="badge-soft success">Activo</span>
                            @else
                                <span class="badge-soft warning">Inactivo</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('combos.edit', $combo) }}"
                                   class="btn-icon-sm"
                                   aria-label="Editar combo {{ $combo->name }}"
                                   title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <form action="{{ route('combos.toggle-active', $combo) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    @if($combo->is_active)
                                        <button type="submit"
                                                class="btn-icon-sm act"
                                                aria-label="Desactivar combo {{ $combo->name }}"
                                                title="Desactivar">
                                            <i class="bi bi-toggle-on"></i>
                                        </button>
                                    @else
                                        <button type="submit"
                                                class="btn-icon-sm warn"
                                                aria-label="Activar combo {{ $combo->name }}"
                                                title="Activar">
                                            <i class="bi bi-toggle-off"></i>
                                        </button>
                                    @endif
                                </form>

                                <form action="{{ route('combos.destroy', $combo) }}" method="POST" class="d-inline" data-confirm-title="¿Eliminar el combo {{ $combo->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-icon-sm warn"
                                            aria-label="Eliminar combo {{ $combo->name }}"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            @if(request()->filled('search') || (request('status') && request('status') !== 'all'))
                                <i class="bi bi-search empty-row-icon"></i>
                                No se encontraron combos
                                <a class="btn btn-outline-brand btn-sm mt-2" href="{{ route('combos.index') }}">Limpiar filtros</a>
                            @else
                                <i class="bi bi-box-seam empty-row-icon"></i>
                                No hay combos registrados
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($combos->hasPages())
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top p-3" style="border-color: var(--border) !important;">
        <span class="text-muted" style="font-size: 0.84rem;">
            Mostrando {{ $combos->firstItem() }}-{{ $combos->lastItem() }} de {{ $combos->total() }}
        </span>
        <nav aria-label="Paginación">
            {{ $combos->links('pagination::bootstrap-5') }}
        </nav>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
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

    document.querySelectorAll('#statusMenu .dropdown-item').forEach(function (item) {
        item.addEventListener('click', function () {
            document.getElementById('filterStatus').value = this.getAttribute('data-value');
            filterForm.submit();
        });
    });
})();
</script>
@endpush
