@extends('layouts.app')

@section('title', 'Inventario')

@section('topbar-actions')
@can('manage-inventory')
<button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#adjustmentModal">
    <i class="bi bi-plus-lg me-1"></i> Ajustar Stock
</button>
@endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="kpi-icon success"><i class="bi bi-arrow-down-circle"></i></div>
                    <div>
                        <p class="kpi-label mb-0">Entradas</p>
                        <strong class="kpi-value">{{ $stats['entradas'] }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="kpi-icon danger"><i class="bi bi-arrow-up-circle"></i></div>
                    <div>
                        <p class="kpi-label mb-0">Salidas</p>
                        <strong class="kpi-value">{{ $stats['salidas'] }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title">Movimientos de Inventario</h2>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-sm-2">
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
                    <option value="entrada" {{ request('type') === 'entrada' ? 'selected' : '' }}>Entrada</option>
                    <option value="salida" {{ request('type') === 'salida' ? 'selected' : '' }}>Salida</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <select name="reason" class="form-select">
                    <option value="">Razón</option>
                    @foreach(['compra'=>'Compra','merma'=>'Merma','ajuste'=>'Ajuste','venta'=>'Venta','devolucion'=>'Devolución','produccion'=>'Producción'] as $v=>$l)
                        <option value="{{ $v }}" {{ request('reason') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <input type="date" name="from" class="form-control" value="{{ request('from') }}" placeholder="Desde">
            </div>
            <div class="col-6 col-sm-2">
                <input type="date" name="to" class="form-control" value="{{ request('to') }}" placeholder="Hasta">
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
                        <th>Tipo</th>
                        <th class="text-end">Cantidad</th>
                        <th>Razón</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $adj)
                    <tr>
                        <td class="text-muted">{{ $adj->created_at->format('d/m/Y h:i a') }}</td>
                        <td>{{ $adj->product->name }}</td>
                        <td>
                            <span class="badge-soft {{ $adj->type === 'entrada' ? 'success' : 'danger' }}">
                                {{ $adj->type_label }}
                            </span>
                        </td>
                        <td class="text-end num {{ $adj->type === 'entrada' ? 'text-success' : 'text-danger' }}">
                            {{ $adj->type === 'entrada' ? '+' : '-' }}{{ $adj->quantity }}
                        </td>
                        <td class="text-muted">{{ $adj->reason_label }}</td>
                        <td class="text-muted">{{ $adj->user->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay movimientos registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center">
            {{ $adjustments->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection

@section('modals')
@can('manage-inventory')
<form method="POST" action="{{ route('inventory.store') }}">
    @csrf
    <div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clipboard2-data me-1"></i> Ajustar Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="adjProduct" class="form-label">Producto *</label>
                        <select class="form-select @error('product_id') is-invalid @enderror" id="adjProduct" name="product_id" required>
                            <option value="">Seleccionar producto</option>
                            @foreach($adjustableProducts as $p)
                                <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} (Stock: {{ $p->stock_current }})</option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo *</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="typeEntrada" value="entrada" {{ old('type', 'entrada') === 'entrada' ? 'checked' : '' }}>
                                <label class="form-check-label" for="typeEntrada">Entrada</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="typeSalida" value="salida" {{ old('type') === 'salida' ? 'checked' : '' }}>
                                <label class="form-check-label" for="typeSalida">Salida</label>
                            </div>
                        </div>
                        @error('type')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="adjQuantity" class="form-label">Cantidad *</label>
                        <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="adjQuantity" name="quantity" min="1" value="{{ old('quantity') }}" required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="adjReason" class="form-label">Razón *</label>
                        <select class="form-select @error('reason') is-invalid @enderror" id="adjReason" name="reason" required>
                            @foreach(['compra'=>'Compra','devolucion'=>'Devolución','ajuste'=>'Ajuste'] as $v=>$l)
                                <option value="{{ $v }}" {{ old('reason') == $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="adjNotes" class="form-label">Notas</label>
                        <input type="text" class="form-control" id="adjNotes" name="notes" value="{{ old('notes') }}" placeholder="Opcional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i> Guardar Ajuste</button>
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
    bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustmentModal')).show();
});
</script>
@endpush
@endif
