@extends('layouts.app')

@section('title', 'Producción')

@section('topbar-actions')
@can('manage-inventory')
<button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#productionModal">
    <i class="bi bi-tools me-1"></i> Registrar Producción
</button>
@endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="kpi-icon success"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <p class="kpi-label mb-0">Producido hoy</p>
                        <strong class="kpi-value">{{ $totalToday }}</strong>
                        <span class="kpi-trend muted">unidades</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title">Registro de Producción</h2>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-sm-4">
                <select name="product_id" class="form-select">
                    <option value="">Todos los productos</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3">
                <input type="date" name="from" class="form-control" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-sm-3">
                <input type="date" name="to" class="form-control" value="{{ request('to') }}">
            </div>
            <div class="col-12 col-sm-2">
                <button type="submit" class="btn btn-outline-brand w-100">Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th class="text-end">Cantidad</th>
                        <th>Notas</th>
                        <th>Usuario</th>
                        @can('manage-inventory')
                        <th class="text-end">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse($productions as $p)
                    <tr>
                        <td class="text-muted">{{ $p->created_at->format('d/m/Y h:i a') }}</td>
                        <td>{{ $p->product->name }}</td>
                        <td class="text-end num text-success">+{{ $p->quantity }}</td>
                        <td class="text-muted">{{ $p->notes ?? '—' }}</td>
                        <td class="text-muted">{{ $p->user->name ?? '—' }}</td>
                        @can('manage-inventory')
                        <td class="text-end">
                            @if($p->isUndoable())
                                <form action="{{ route('productions.destroy', $p) }}" method="POST" class="d-inline"
                                      data-confirm-title="¿Eliminar este registro de producción?"
                                      data-confirm-text="Se descontarán {{ $p->quantity }} unidades de {{ $p->product->name }} del stock."
                                      data-confirm-button="Sí, eliminar">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon-sm warn"
                                            aria-label="Eliminar registro de producción de {{ $p->product->name }}"
                                            title="Eliminar (disponible por 20 minutos)">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ auth()->user()->can('manage-inventory') ? 6 : 5 }}" class="text-center text-muted py-4">No hay producción registrada.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($productions->hasPages())
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top p-3">
            <span class="text-muted small">
                Mostrando {{ $productions->firstItem() }}-{{ $productions->lastItem() }} de {{ $productions->total() }}
            </span>
            <nav aria-label="Paginación">
                {{ $productions->withQueryString()->links() }}
            </nav>
        </div>
        @endif
    </div>
</div>
@endsection

@section('modals')
@can('manage-inventory')
<form method="POST" action="{{ route('productions.store') }}">
    @csrf
    <div class="modal fade" id="productionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tools me-1"></i> Registrar Producción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="prodProduct" class="form-label">Producto *</label>
                        <select class="form-select @error('product_id') is-invalid @enderror" id="prodProduct" name="product_id" required>
                            <option value="">Seleccionar producto</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="prodQuantity" class="form-label">Cantidad producida *</label>
                        <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="prodQuantity" name="quantity" min="1" value="{{ old('quantity') }}" required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="prodNotes" class="form-label">Notas</label>
                        <textarea class="form-control" id="prodNotes" name="notes" rows="2" placeholder="Opcional">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i> Registrar</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endcan
@endsection
