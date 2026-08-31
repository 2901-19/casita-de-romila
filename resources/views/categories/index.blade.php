@extends('layouts.app')

@section('title', 'Categorías')

@section('topbar-actions')
@can('manage-products')
<button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#categoryModal" data-mode="create">
    <i class="bi bi-plus-lg me-1"></i>Nueva Categoría
</button>
@endcan
@endsection

@section('content')


<div class="card">
    <div class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-center" style="border-color: var(--border) !important;">

        <div class="search-box">
            <i class="bi bi-search search-icon" aria-hidden="true"></i>
            <form method="GET" action="{{ route('categories.index') }}" id="searchForm">
                <input type="search" class="form-control" name="search" placeholder="Buscar categoría..." value="{{ request('search') }}" aria-label="Buscar categoría">
                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            </form>
        </div>
    </div>

    <div id="tableWrap">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col" class="text-end">Productos</th>
                        @can('manage-products')
                        <th scope="col" class="text-end">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td>
                            <span class="cat-name">{{ $category->name }}</span>
                        </td>
                        <td class="text-end">
                            <span class="count-pill">
                                <i class="bi bi-box-seam"></i>
                                {{ $category->products_count }} producto{{ $category->products_count !== 1 ? 's' : '' }}
                            </span>
                        </td>
                        @can('manage-products')
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button class="btn-icon-sm"
                                        aria-label="Editar {{ $category->name }}"
                                        title="Editar"
                                        data-bs-toggle="modal"
                                        data-bs-target="#categoryModal"
                                        data-mode="edit"
                                        data-id="{{ $category->id }}"
                                        data-name="{{ $category->name }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon-sm warn"
                                        aria-label="Eliminar {{ $category->name }}"
                                        title="Eliminar"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal"
                                        data-id="{{ $category->id }}"
                                        data-name="{{ $category->name }}"
                                        data-count="{{ $category->products_count }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-5">
                            @if(request()->filled('search') || (request('status') && request('status') !== 'all'))
                                <i class="bi bi-search d-block" style="font-size:2rem;margin-bottom:0.5rem;opacity:0.4;"></i>
                                No se encontraron categorías
                                <a class="btn btn-outline-brand btn-sm mt-2" href="{{ route('categories.index') }}">Limpiar filtros</a>
                            @else
                                <i class="bi bi-tags d-block" style="font-size:2rem;margin-bottom:0.5rem;opacity:0.4;"></i>
                                No hay categorías registradas
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($categories->hasPages())
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top p-3" style="border-color: var(--border) !important;">
        <span class="text-muted" style="font-size: 0.84rem;">
            Mostrando {{ $categories->firstItem() }}-{{ $categories->lastItem() }} de {{ $categories->total() }}
        </span>
        <nav aria-label="Paginación">
            {{ $categories->links('pagination::bootstrap-5') }}
        </nav>
    </div>
    @endif
</div>
@endsection

@section('modals')
@can('manage-products')
<form method="POST" id="categoryForm">
    @csrf
    @method('PUT')
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Nombre</label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="categoryName"
                               name="name"
                               placeholder="Ej. Bebidas"
                               required
                               maxlength="100">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<form method="POST" id="deleteForm">
    @csrf
    @method('DELETE')
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <div class="modal-body pt-4 pb-3 text-center">
                    <div class="danger-icon" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h5 class="confirm-title mb-2">¿Eliminar la categoría <span id="deleteCatName" class="text-brand"></span>?</h5>
                    <p class="confirm-text mb-1" id="deleteCatText"></p>
                    <p class="confirm-sub mb-0">Los productos no se eliminarán, pero quedarán sin categoría.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger-soft">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endcan
@endsection

@push('scripts')
@can('manage-products')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var catModal = document.getElementById('categoryModal');
    var catForm = document.getElementById('categoryForm');
    var catTitle = document.getElementById('categoryModalTitle');
    var catName = document.getElementById('categoryName');

    catModal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        var mode = trigger ? trigger.getAttribute('data-mode') : 'create';
        var methodInput = catForm.querySelector('input[name="_method"]');

        if (mode === 'edit') {
            var name = trigger.getAttribute('data-name');
            var id = trigger.getAttribute('data-id');
            catTitle.textContent = 'Editar Categoría';
            catName.value = name;
            catForm.action = '/categories/' + id;
            if (!methodInput) {
                methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                catForm.appendChild(methodInput);
            }
            methodInput.value = 'PUT';
        } else {
            catTitle.textContent = 'Nueva Categoría';
            catName.value = '';
            catForm.action = '/categories';
            if (methodInput) {
                methodInput.remove();
            }
        }
    });

    var deleteModal = document.getElementById('deleteModal');
    var deleteForm = document.getElementById('deleteForm');

    deleteModal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        var name = trigger.getAttribute('data-name');
        var id = trigger.getAttribute('data-id');
        var count = parseInt(trigger.getAttribute('data-count'), 10);

        document.getElementById('deleteCatName').textContent = name;
        document.getElementById('deleteCatText').textContent =
            'Esta categoría tiene ' + count + ' producto' + (count !== 1 ? 's' : '') + ' asociados.';

        deleteForm.action = '/categories/' + id;
    });

    var searchForm = document.getElementById('searchForm');
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
});
</script>
@endcan
@endpush
