@extends('layouts.app')

@section('title', 'Mermas')

@section('topbar-actions')
@can('manage-waste')
<button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#mermaModal">
    <i class="bi bi-exclamation-triangle me-1"></i> Reportar Merma
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
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title">Registro de Mermas</h2>
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
            <div class="col-6 col-sm-2">
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
                        <th>Razón</th>
                        <th>Notas</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mermas as $m)
                    <tr>
                        <td class="text-muted">{{ $m->created_at->format('d/m/Y h:i a') }}</td>
                        <td>{{ $m->product->name }}</td>
                        <td class="text-end num text-danger">-{{ $m->quantity }}</td>
                        <td><span class="badge-soft danger">{{ $m->reason_label }}</span></td>
                        <td class="text-muted">{{ $m->notes ?? '—' }}</td>
                        <td class="text-muted">{{ $m->user->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay mermas registradas.</td>
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
<form method="POST" action="{{ route('mermas.store') }}">
    @csrf
    <div class="modal fade" id="mermaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-1"></i> Reportar Merma</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mermaProduct" class="form-label">Producto *</label>
                        <select class="form-select @error('product_id') is-invalid @enderror" id="mermaProduct" name="product_id" required>
                            <option value="">Seleccionar producto</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} (Stock: {{ $p->stock_current }})</option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="mermaQuantity" class="form-label">Cantidad *</label>
                        <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="mermaQuantity" name="quantity" min="1" value="{{ old('quantity') }}" required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="mermaReason" class="form-label">Razón *</label>
                        <select class="form-select @error('reason') is-invalid @enderror" id="mermaReason" name="reason" required>
                            <option value="vencido" {{ old('reason') === 'vencido' ? 'selected' : '' }}>Vencido</option>
                            <option value="danado" {{ old('reason') === 'danado' ? 'selected' : '' }}>Dañado</option>
                            <option value="otro" {{ old('reason') === 'otro' ? 'selected' : '' }}>Otro</option>
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
                    <button type="submit" class="btn btn-brand"><i class="bi bi-check2-circle me-1"></i> Registrar Merma</button>
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
