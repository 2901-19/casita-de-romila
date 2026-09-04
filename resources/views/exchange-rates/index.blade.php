@extends('layouts.app')

@section('title', 'Tasa BCV')

@section('topbar-actions')
@can('manage-exchange-rates')
<button class="btn btn-brand" type="button" id="btnNuevaTasa">
    <i class="bi bi-arrow-repeat me-1"></i> Actualizar Tasa
</button>
@endcan
@endsection

@section('content')
<div class="rates-grid single mb-3">
    <div class="card rate-card vigente" id="rateCard">
        <div class="rate-top">
            <span class="rate-currency"><span class="flag">🇻🇪</span>USD — Tasa BCV</span>
            @can('manage-exchange-rates')
            <button class="rate-edit" id="btnEditRate" aria-label="Editar tasa">
                <i class="bi bi-pencil"></i>
            </button>
            @endcan
        </div>
        <div class="rate-value">
            <span class="unit">Bs</span>
            <span id="rateValueDisplay">{{ $latestRate ? number_format($latestRate->rate, 2, ',', '.') : '—' }}</span>
        </div>
        <div class="rate-name-row">
            <span class="badge-soft success">Vigente</span>
            <span class="badge-soft {{ $latestRate ? 'success' : 'muted' }}" id="sourceBadge">
                {{ $latestRate ? $latestRate->source_label : '—' }}
            </span>
        </div>
        <div class="rate-updated">
            <i class="bi bi-clock"></i>
            <span id="rateUpdatedAt">
                @if($latestRate)
                    {{ $latestRate->created_at->format('d/m/Y h:i A') }}
                @else
                    Sin datos
                @endif
            </span>
        </div>
    </div>
</div>

<div class="card active-rate mb-3">
    <span class="badge-soft success">
        <i class="bi bi-check-circle-fill"></i> Tasa BCV activa
    </span>
    <span class="ar-value">
        Bs {{ $latestRate ? number_format($latestRate->rate, 2, ',', '.') : '—' }}
        <small>· por USD</small>
    </span>
    <span class="ar-hint">Esta tasa se usa como referencia USD en el POS</span>
</div>

<div class="card">
    <div class="p-3 border-bottom">
        <h2 class="history-title">Historial de tasas</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="text-end">Tasa (Bs/USD)</th>
                    <th>Fuente</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $rate)
                <tr>
                    <td class="text-muted">{{ $rate->created_at->format('d/m/Y h:i A') }}</td>
                    <td class="text-end num">Bs {{ number_format($rate->rate, 2, ',', '.') }}</td>
                    <td>
                        <span class="badge-soft {{ $rate->source === 'bcv' ? 'success' : ($rate->source === 'manual' ? 'muted' : 'warning') }}">
                            {{ $rate->source_label }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="text-center text-muted py-4">No hay registro de tasas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($history->hasPages())
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top p-3">
        <span class="text-muted small">
            Mostrando {{ $history->firstItem() }}-{{ $history->lastItem() }} de {{ $history->total() }}
        </span>
        <nav aria-label="Paginación">
            {{ $history->links() }}
        </nav>
    </div>
    @endif
</div>
@endsection

@section('modals')
@can('manage-exchange-rates')
<form method="POST" action="{{ route('exchange-rates.store') }}" id="tasaForm">
    @csrf
    <div class="modal fade" id="tasaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tasaModalTitle">Actualizar Tasa BCV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tasaValor" class="form-label">Tasa BCV (Bs por USD)</label>
                        <div class="input-group">
                            <span class="input-group-text">Bs</span>
                            <input type="number"
                                   class="form-control rate-amount @error('rate') is-invalid @enderror"
                                   id="tasaValor"
                                   name="rate"
                                   min="0.01"
                                   step="0.01"
                                   placeholder="0,00"
                                   value="{{ old('rate') }}"
                                   required>
                            @error('rate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tasaFuente" class="form-label">Fuente</label>
                        <select class="form-select @error('source') is-invalid @enderror"
                                id="tasaFuente"
                                name="source"
                                required>
                            <option value="bcv" {{ old('source') === 'bcv' ? 'selected' : '' }}>BCV (Oficial)</option>
                            <option value="paralelo" {{ old('source') === 'paralelo' ? 'selected' : '' }}>Paralelo</option>
                            <option value="binance" {{ old('source') === 'binance' ? 'selected' : '' }}>Binance USDT</option>
                            <option value="enzona" {{ old('source') === 'enzona' ? 'selected' : '' }}>EnZona</option>
                            <option value="manual" {{ old('source') === 'manual' ? 'selected' : '' }}>Manual</option>
                        </select>
                        @error('source')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand">
                        <i class="bi bi-check2-circle me-1"></i> Actualizar Tasa
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endcan
@endsection

@push('scripts')
@can('manage-exchange-rates')
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var modal = new bootstrap.Modal(document.getElementById('tasaModal'));
        var valorEl = document.getElementById('tasaValor');
        var fuenteEl = document.getElementById('tasaFuente');

        function openModal() {
            valorEl.value = '';
            fuenteEl.value = 'bcv';
            modal.show();
        }

        document.getElementById('btnNuevaTasa').addEventListener('click', openModal);
        document.getElementById('btnEditRate').addEventListener('click', openModal);
    });
})();
</script>
@endcan
@endpush
