@extends('layouts.app')

@section('title', 'Comandas')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title">Comandas</h2>
            <a href="{{ route('comandas.create') }}" class="btn btn-brand btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Nueva Comanda
            </a>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-6 col-sm-2">
                <select name="status" class="form-select">
                    <option value="">Estado</option>
                    <option value="montada" {{ request('status') === 'montada' ? 'selected' : '' }}>Montada</option>
                    <option value="entregada" {{ request('status') === 'entregada' ? 'selected' : '' }}>Entregada</option>
                    <option value="cobrada" {{ request('status') === 'cobrada' ? 'selected' : '' }}>Cobrada</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <select name="order_type" class="form-select">
                    <option value="">Tipo</option>
                    <option value="delivery" {{ request('order_type') === 'delivery' ? 'selected' : '' }}>Delivery</option>
                    <option value="local" {{ request('order_type') === 'local' ? 'selected' : '' }}>Consumo local</option>
                    <option value="para_llevar" {{ request('order_type') === 'para_llevar' ? 'selected' : '' }}>Para llevar</option>
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <button type="submit" class="btn btn-outline-brand w-100">Filtrar</button>
            </div>
            <div class="col-6 col-sm-2">
                <a href="{{ route('comandas.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Comanda</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Items</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comandas as $comanda)
                    <tr>
                        <td class="num">#{{ $comanda->comanda_number }}</td>
                        <td class="text-muted">{{ $comanda->created_at->format('d/m/Y h:i a') }}</td>
                        <td>
                            @foreach($comanda->typeBadges() as $tb)
                                <span class="badge-soft {{ $tb['badge'] }} mb-1">{{ $tb['label'] }}</span>
                            @endforeach
                            @if($comanda->hasDeliveryItems() && $comanda->customer_name)
                                <span class="d-block text-muted small">{{ $comanda->customer_name }}</span>
                            @endif
                        </td>
                        <td>{{ $comanda->items->count() }} {{ $comanda->items->count() === 1 ? 'item' : 'items' }}
                            ({{ $comanda->deliveredItemsCount() }}/{{ $comanda->items->count() }} entregados)</td>
                        <td class="text-end num">Bs {{ number_format($comanda->total_bs, 2, ',', '.') }}</td>
                        <td>
                            <span class="badge-soft {{ $comanda->status_color }}">{{ $comanda->status_label }}</span>
                        </td>
                        <td>
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('comandas.show', $comanda) }}"
                                   class="btn-icon-sm"
                                   aria-label="Ver comanda #{{ $comanda->comanda_number }}"
                                   title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($comanda->status !== 'cobrada')
                                <a href="{{ route('comandas.show', $comanda) }}" class="btn-icon-sm" title="Cobrar">
                                    <i class="bi bi-cash-coin"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-journal-text empty-row-icon"></i>
                            No hay comandas.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center">
            {{ $comandas->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
