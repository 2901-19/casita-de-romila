@extends('layouts.app')

@section('title', 'Comanda #' . $comanda->comanda_number)

@section('topbar-actions')
<a href="{{ route('comandas.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Volver
</a>
@endsection

@section('content')
@php($isCobrada = $comanda->status === 'cobrada')
@php($canEdit = !$isCobrada)
@php($pendingTotal = $comanda->pendingTotal())
@php($collectedTotal = $comanda->collectedTotal())
@php($fullyCollected = $comanda->isFullyCollected())
@php($hasCashPayments = $comanda->payments->where('method', '!=', 'credito')->isNotEmpty())
@php($hasCreditPayments = $comanda->payments->where('method', 'credito')->isNotEmpty())
@php($allDelivered = $comanda->allItemsDelivered())

<div class="show-comanda-wrap" x-data="comandaShowApp()">

    {{-- HEADER --}}
    <div class="card comanda-header mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <h1 class="card-title h5 mb-1">Comanda {{ $comanda->comanda_number }}</h1>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="badge-soft {{ $comanda->status_color }}">{{ $comanda->status_label }}</span>
                            @foreach($comanda->typeBadges() as $tb)
                                <span class="badge-soft {{ $tb['badge'] }}"><i class="bi bi-bag me-1"></i>{{ $tb['label'] }}</span>
                            @endforeach
                            <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ $comanda->created_at->format('d/m/Y h:i a') }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Total a cancelar</div>
                    <h2 class="mb-0 h4">
                        Bs <span class="num">{{ number_format($comanda->total_bs, 2, ',', '.') }}</span>
                    </h2>
                    <small class="text-muted">≈ ${{ number_format($comanda->total_usd, 2, ',', '.') }} USD</small>
                </div>
            </div>

            @if(($comanda->hasDeliveryItems() && $comanda->customer_name) || $comanda->sale || $comanda->user)
            <hr class="my-3">
            <div class="row g-2">
                @if($comanda->hasDeliveryItems() && $comanda->customer_name)
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block small"><i class="bi bi-person me-1"></i>Cliente</span>
                    <strong>{{ $comanda->customer_name }}</strong>
                </div>
                @endif
                @if($comanda->sale)
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block small"><i class="bi bi-receipt me-1"></i>Venta generada</span>
                    <a href="{{ route('sales.show', $comanda->sale) }}">#{{ $comanda->sale->id }}</a>
                </div>
                @endif
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block small"><i class="bi bi-person-badge me-1"></i>Registrada por</span>
                    <strong>{{ $comanda->user?->name }}</strong>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- STEPPER --}}
    @php($isMontada = $comanda->status === 'montada')
    @php($isEntregada = $comanda->status === 'entregada')
    <div class="card comanda-stepper-card mb-3">
        <div class="card-body py-3">
            <ol class="stepper">
                <li class="step {{ $isCobrada || $isEntregada || $allDelivered ? 'done' : 'active' }}">
                    <span class="step-dot"><i class="bi bi-journal-text"></i></span>
                    <div class="step-body">
                        <span class="step-label">Montada</span>
                        <span class="step-sub">Comanda creada</span>
                    </div>
                </li>
                <li class="step {{ $isCobrada || $isEntregada ? 'done' : ($allDelivered ? 'active' : '') }}">
                    <span class="step-dot"><i class="bi bi-basket"></i></span>
                    <div class="step-body">
                        <span class="step-label">Entregada</span>
                        <span class="step-sub">Seguimiento de entrega</span>
                    </div>
                </li>
                <li class="step {{ $isCobrada ? 'done' : '' }}">
                    <span class="step-dot"><i class="bi bi-cash-coin"></i></span>
                    <div class="step-body">
                        <span class="step-label">Cobrada</span>
                        <span class="step-sub">{{ $isCobrada ? 'Venta registrada' : 'Puede cobrar en cualquier momento' }}</span>
                    </div>
                </li>
            </ol>
        </div>
    </div>

    {{-- ITEMS --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="card-title h6 mb-0">Items</h2>
                    <span class="card-sub">{{ $comanda->deliveredItemsCount() }}/{{ $comanda->items->count() }} entregados</span>
                </div>
                @if($canEdit && $comanda->status === 'montada' && !$allDelivered)
                <form method="POST" action="{{ route('comandas.mark-delivered', $comanda) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-check2-all me-1"></i> Marcar toda entregada
                    </button>
                </form>
                @endif
            </div>

            <div class="comanda-items">
                @forelse($comanda->items as $item)
                <div class="comanda-item {{ $item->isDelivered() ? 'is-delivered' : '' }}">
                    <div class="ci-main">
                        <div class="ci-namewrap">
                            <span class="ci-name">{{ $item->product_name }}</span>
                            <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                                <span class="badge-soft {{ $item->order_type_badge }}"><i class="bi bi-bag me-1"></i>{{ $item->order_type_label }}</span>
                                @if($item->collected)
                                <span class="badge-soft success"><i class="bi bi-check-circle me-1"></i>Cobrado</span>
                                @endif
                            </div>
                            @if($item->note)
                            <span class="ci-unit text-muted small d-block mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $item->note }}</span>
                            @endif
                        </div>
                        <div class="ci-amount text-end">
                            <div class="ci-qty">
                                @if($item->isDelivered())
                                <span class="badge-soft success"><i class="bi bi-check-circle me-1"></i>Completo</span>
                                @else
                                <span class="ci-qty-label">Entregadas <strong>{{ $item->delivered_quantity }}/{{ $item->quantity }}</strong></span>
                                @endif
                            </div>
                            <span class="ci-subtotal">Bs {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="ci-progress">
                        @php($pct = $item->quantity > 0 ? (int) round($item->delivered_quantity / $item->quantity * 100) : 0)
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar" style="width: {{ $pct }}%;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small text-muted">{{ $item->delivered_quantity }} de {{ $item->quantity }} {{ Str::plural('unidad', $item->quantity) }} entregadas</span>
                            @if(!$item->isDelivered() && !$isCobrada)
                            <form method="POST" action="{{ route('comandas.deliver-item', [$comanda, $item]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-check2 me-1"></i> Entregar +1
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-4">La comanda no tiene items.</div>
                @endforelse
            </div>

            <hr class="my-3">
            <div class="d-flex justify-content-end align-items-center gap-3 flex-wrap">
                <div class="text-end">
                    <div class="text-muted small">Cobrado</div>
                    <strong>Bs <span class="num">{{ number_format($collectedTotal, 2, ',', '.') }}</span></strong>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Pendiente</div>
                    <strong class="{{ $pendingTotal > 0 ? 'text-danger' : 'text-success' }}">Bs <span class="num">{{ number_format($pendingTotal, 2, ',', '.') }}</span></strong>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Total</div>
                    <strong class="fs-5">Bs <span class="num">{{ number_format($comanda->total_bs, 2, ',', '.') }}</span></strong>
                </div>
            </div>
        </div>
    </div>

    {{-- ACTIONS --}}
    @if(!$isCobrada)
    <div class="card comanda-actions-card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                @if($fullyCollected)
                <i class="bi bi-check-circle me-1 text-success"></i>Comanda cobrada en su totalidad. Ciérrala para registrar la venta.
                @elseif($pendingTotal > 0)
                <i class="bi bi-info-circle me-1"></i>Pendiente de cobro: <strong>Bs {{ number_format($pendingTotal, 2, ',', '.') }}</strong> — puedes cobrar en cualquier momento.
                @else
                <i class="bi bi-info-circle me-1"></i>Comanda sin items por cobrar.
                @endif
            </div>
            <div class="d-inline-flex gap-2">
                @if($canEdit)
                <button type="button" class="btn btn-outline-brand" data-bs-toggle="modal" data-bs-target="#editModal">
                    <i class="bi bi-pencil me-1"></i> Editar
                </button>
                @endif
                @if($canEdit && $pendingTotal > 0)
                <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#collectModal">
                    <i class="bi bi-cash-coin me-1"></i> Cobrar pendientes
                </button>
                @endif
                @if($canEdit && $fullyCollected)
                <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#closeModal">
                    <i class="bi bi-check2-circle me-1"></i> Cerrar comanda
                </button>
                @endif
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-check-circle-fill me-1"></i>
        <span>Comanda cobrada y cerrada.</span>
        @if($comanda->sale)
        <a href="{{ route('sales.show', $comanda->sale) }}" class="ms-auto">Ver venta #{{ $comanda->sale->id }}</a>
        @endif
    </div>
    @endif

    {{-- COLLECT MODAL --}}
    @if($canEdit && $pendingTotal > 0)
    <div class="modal fade" id="collectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('comandas.collect', $comanda) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-cash-coin me-1"></i> Cobrar pendientes #{{ $comanda->comanda_number }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div class="text-muted small">Monto pendiente a cobrar</div>
                            <h3 class="mb-0">Bs {{ number_format($pendingTotal, 2, ',', '.') }}</h3>
                            <small class="text-muted">≈ ${{ number_format($pendingTotal / ($rate > 0 ? $rate : 1), 2, ',', '.') }} USD</small>
                        </div>

                        <label class="form-label mb-2">Método de pago</label>
                        <div class="row g-2 mb-3">
                            @php($methods = ['efectivo' => ['Efectivo', 'bi-cash'], 'biopago' => ['Biopago', 'bi-qr-code'], 'pago_movil' => ['Pago Móvil', 'bi-phone'], 'pdv' => ['PDV', 'bi-credit-card-2-front'], 'credito' => ['Crédito', 'bi-journal-bookmark']])
                            @foreach($methods as $value => [$label, $icon])
                            <div class="col-6">
                                <label class="payment-option d-flex align-items-center gap-2" :class="{ 'selected': paymentMethod === '{{ $value }}' }">
                                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="paymentMethod"
                                           :disabled="{{ $value === 'credito' && $hasCashPayments ? 'true' : ($value !== 'credito' && $hasCreditPayments ? 'true' : 'false') }}">
                                    <i class="bi {{ $icon }}"></i> {{ $label }}
                                </label>
                            </div>
                            @endforeach
                        </div>

                        <div x-show="paymentMethod === 'credito'" x-cloak>
                            <label for="collectCustomer" class="form-label mb-1">Cliente</label>
                            <select id="collectCustomer" name="customer_id" class="form-select">
                                <option value="">Seleccione un cliente...</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-brand">Registrar cobro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- CLOSE MODAL --}}
    @if($canEdit && $fullyCollected)
    <div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('comandas.close', $comanda) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-check2-circle me-1"></i> Cerrar comanda #{{ $comanda->comanda_number }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-0">
                            Se registrará la <strong>venta</strong> por <strong>Bs {{ number_format($comanda->total_bs, 2, ',', '.') }}</strong>
                            y la comanda quedará <strong>cobrada</strong>. Esta acción no se puede deshacer.
                        </div>
                        <div class="text-muted small mt-2">
                            Cobros registrados:
                            @forelse($comanda->payments as $pay)
                                <span class="d-block mt-1"><i class="bi bi-cash me-1"></i>{{ $pay->method_label }} — Bs {{ number_format($pay->amount, 2, ',', '.') }}</span>
                            @empty
                                <span class="d-block mt-1">Ninguno</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-brand">Cerrar y registrar venta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- EDIT MODAL --}}
    @if($canEdit)
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('comandas.update', $comanda) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-1"></i> Editar comanda #{{ $comanda->comanda_number }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label mb-1"><i class="bi bi-bag me-1"></i> Aplicar tipo a todos (editable)</label>
                                <select class="form-select form-select-sm" x-model="defaultOrderType" @change="applyEditOrderTypeToAll()">
                                    <option value="local">Consumo local</option>
                                    <option value="para_llevar">Para llevar</option>
                                    <option value="delivery">Delivery</option>
                                </select>
                            </div>
                            <div class="col-6" x-show="editHasDeliveryLine" x-cloak>
                                <label class="form-label mb-1">Nombre (delivery)</label>
                                <input type="text" name="customer_name" class="form-control form-control-sm" x-model="customerName" placeholder="Opcional">
                            </div>
                        </div>

                        <div class="mb-2 d-flex align-items-center gap-2">
                            <input type="search" class="form-control" placeholder="Buscar producto para agregar..." x-model="editSearch">
                            <span class="text-muted small" x-text="editableLines().length + ' editable(s)'"></span>
                        </div>

                        <div class="table-responsive mb-3 scroll-viewport">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                    <template x-for="product in editFiltered" :key="product.id">
                                        <tr>
                                            <td style="width:48px;">
                                                <template x-if="product.image">
                                                    <img :src="product.image" alt="" class="pos-thumb" loading="lazy">
                                                </template>
                                                <template x-if="!product.image">
                                                    <span class="pos-thumb pos-thumb-placeholder"><i class="bi bi-box"></i></span>
                                                </template>
                                            </td>
                                            <td>
                                                <span x-text="product.name"></span>
                                                <template x-if="product.is_combo"><span class="badge-soft success ms-1">Combo</span></template>
                                                <template x-if="product.is_demanda"><span class="badge-soft info ms-1">Demanda</span></template>
                                            </td>
                                            <td class="text-end" style="width:90px;">
                                                <button type="button" class="btn btn-sm btn-brand" @click="addEditProduct(product)">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr><th>Item</th><th class="text-center">Cant</th><th>Tipo</th><th>Nota</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <template x-for="item in editLines" :key="item.key">
                                        <tr :class="{ 'table-light': item.collected }">
                                            <td>
                                                <template x-if="!item.collected">
                                                    <span>
                                                        <input type="hidden" :name="'cart[' + item.key + '][product_id]'" :value="item.product_id">
                                                        <input type="hidden" :name="'cart[' + item.key + '][quantity]'" :value="item.quantity">
                                                        <input type="hidden" :name="'cart[' + item.key + '][order_type]'" :value="item.order_type">
                                                        <input type="hidden" :name="'cart[' + item.key + '][note]'" :value="item.note">
                                                        <span x-text="item.name"></span>
                                                    </span>
                                                </template>
                                                <template x-if="item.collected">
                                                    <span class="text-muted">
                                                        <span x-text="item.name"></span>
                                                        <span class="badge-soft success ms-1"><i class="bi bi-lock me-1"></i>Cobrado</span>
                                                    </span>
                                                </template>
                                            </td>
                                            <td class="text-center">
                                                <template x-if="!item.collected">
                                                    <div class="d-inline-flex align-items-center gap-1">
                                                        <button type="button" class="qty-btn" @click="decEditQty(item.key)"><i class="bi bi-dash"></i></button>
                                                        <input type="number" class="qty-input" style="width:46px;" :value="item.quantity" min="1" readonly>
                                                        <button type="button" class="qty-btn" @click="encEditQty(item.key)"><i class="bi bi-plus"></i></button>
                                                    </div>
                                                </template>
                                                <template x-if="item.collected">
                                                    <span class="badge-soft muted" x-text="'×' + item.quantity"></span>
                                                </template>
                                            </td>
                                            <td>
                                                <template x-if="!item.collected">
                                                    <select class="form-select form-select-sm" style="width:140px;" x-model="item.order_type">
                                                        <option value="local">Consumo local</option>
                                                        <option value="para_llevar">Para llevar</option>
                                                        <option value="delivery">Delivery</option>
                                                    </select>
                                                </template>
                                                <template x-if="item.collected">
                                                    <span class="badge-soft muted" x-text="orderLabel(item.order_type)"></span>
                                                </template>
                                            </td>
                                            <td>
                                                <template x-if="!item.collected">
                                                    <input type="text" class="form-control form-control-sm" x-model="item.note" placeholder="Opcional" style="min-width:120px;">
                                                </template>
                                                <template x-if="item.collected">
                                                    <span class="text-muted small" x-text="item.note || '—'"></span>
                                                </template>
                                            </td>
                                            <td class="text-end">
                                                <template x-if="!item.collected">
                                                    <button type="button" class="remove-btn" @click="removeEditLine(item.key)" aria-label="Quitar"><i class="bi bi-trash"></i></button>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="editableLines().length === 0">
                                        <td colspan="5" class="text-center text-muted py-3">
                                            Todos los items están cobrados. Solo puedes agregar items nuevos.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-brand" :disabled="editableLines().length === 0">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', function () {
    Alpine.data('comandaShowApp', function () {
        return {
            paymentMethod: 'efectivo',
            customerName: @json($comanda->customer_name),
            editSearch: '',
            nextEditKey: 0,
            defaultOrderType: 'local',
            editLines: [
                @foreach($comanda->items as $item)
                {
                    key: 'i{{ $item->id }}',
                    product_id: '{{ $item->combo_id ? 'combo_' . $item->combo_id : $item->product_id }}',
                    name: {!! json_encode($item->product_name) !!},
                    quantity: {{ $item->quantity }},
                    order_type: {!! json_encode($item->order_type) !!},
                    note: {!! json_encode($item->note) !!},
                    collected: {{ $item->collected ? 'true' : 'false' }},
                    is_demanda: {{ ($item->combo_id || !$item->product_id) ? 'false' : (($products->firstWhere('id', $item->product_id)?->control_type === 'demanda') ? 'true' : 'false') }},
                },
                @endforeach
            ],

            get products() {
                return [
                    @foreach($products as $p)
                        { id: {{ $p->id }}, name: {!! json_encode($p->name) !!}, sale_price: {{ number_format($p->sale_price * $rate, 2, '.', '') }}, category_id: {{ $p->category_id ?? 'null' }}, image: {!! json_encode($p->image ? asset('storage/'.$p->image) : '') !!}, is_demanda: {{ $p->control_type === 'demanda' ? 'true' : 'false' }} },
                    @endforeach
                ];
            },
            get combos() {
                return [
                    @foreach($combos as $combo)
                        { id: 'combo_{{ $combo->id }}', name: {!! json_encode($combo->name) !!}, is_combo: true, is_demanda: false },
                    @endforeach
                ];
            },
            get allItems() {
                return [...this.products, ...this.combos];
            },
            get editFiltered() {
                return this.allItems.filter(p => this.editSearch === '' || p.name.toLowerCase().includes(this.editSearch.toLowerCase()));
            },
            get editHasDeliveryLine() {
                return this.editLines.some(l => l.order_type === 'delivery');
            },

            editableLines() {
                return this.editLines.filter(l => !l.collected);
            },

            addEditProduct(product) {
                if (!product.is_demanda) {
                    let line = this.editLines.find(l => String(l.product_id) === String(product.id) && !l.is_demanda && !l.collected);
                    if (line) { line.quantity++; return; }
                }
                this.editLines.push({
                    key: 'n' + (this.nextEditKey++),
                    product_id: product.id,
                    name: product.name,
                    quantity: 1,
                    order_type: this.defaultOrderType,
                    note: '',
                    collected: false,
                    is_demanda: product.is_demanda || false,
                });
            },
            encEditQty(key) {
                let line = this.editLines.find(l => l.key === key);
                if (line && !line.collected) { line.quantity++; }
            },
            decEditQty(key) {
                let line = this.editLines.find(l => l.key === key);
                if (!line || line.collected) { return; }
                if (line.quantity > 1) { line.quantity--; } else { this.removeEditLine(key); }
            },
            removeEditLine(key) {
                this.editLines = this.editLines.filter(l => l.key !== key || l.collected);
            },
            applyEditOrderTypeToAll() {
                this.editLines.forEach(l => { if (!l.collected) { l.order_type = this.defaultOrderType; } });
            },
            orderLabel(t) {
                return { delivery: 'Delivery', local: 'Consumo local', para_llevar: 'Para llevar' }[t] || t;
            },
        };
    });
});
</script>
@endpush