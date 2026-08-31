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
@php($canCollect = $comanda->is_delivery || $comanda->status === 'entregada')
@php($allDelivered = $comanda->allItemsDelivered())

<div class="show-comanda-wrap" x-data="comandaShowApp()">

    {{-- HEADER --}}
    <div class="card comanda-header mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="comanda-num">#{{ $comanda->comanda_number }}</div>
                    <div>
                        <h1 class="card-title h5 mb-1">Comanda {{ $comanda->comanda_number }}</h1>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="badge-soft {{ $comanda->status_color }}">{{ $comanda->status_label }}</span>
                            <span class="text-muted small"><i class="bi bi-bag me-1"></i>{{ $comanda->order_type_label }}</span>
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

            @if($comanda->customer_name || $comanda->notes || $comanda->sale || $comanda->user)
            <hr class="my-3">
            <div class="row g-2">
                @if($comanda->customer_name)
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block small"><i class="bi bi-person me-1"></i>Cliente</span>
                    <strong>{{ $comanda->customer_name }}</strong>
                </div>
                @endif
                @if($comanda->notes)
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block small"><i class="bi bi-chat-left-text me-1"></i>Nota cocina</span>
                    <strong>{{ $comanda->notes }}</strong>
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
                        <span class="step-sub">{{ $allDelivered ? 'Lista para cobrar' : 'En preparación' }}</span>
                    </div>
                </li>
                <li class="step {{ $isCobrada ? 'done' : '' }}">
                    <span class="step-dot"><i class="bi bi-cash-coin"></i></span>
                    <div class="step-body">
                        <span class="step-label">Cobrada</span>
                        <span class="step-sub">{{ $isCobrada ? 'Pagada' : 'Pendiente' }}</span>
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
                            <span class="ci-unit text-muted small">Bs {{ number_format($item->unit_price, 2, ',', '.') }} c/u</span>
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
            <div class="d-flex justify-content-end align-items-center gap-2">
                <span class="text-muted">Total</span>
                <strong class="fs-5">Bs <span class="num">{{ number_format($comanda->total_bs, 2, ',', '.') }}</span></strong>
            </div>
        </div>
    </div>

    {{-- ACTIONS --}}
    @if(!$isCobrada)
    <div class="card comanda-actions-card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                @if($comanda->is_delivery && !$allDelivered && !$isEntregada)
                <i class="bi bi-info-circle me-1"></i>Pedido delivery: puedes cobrar por adelantado.
                @elseif(!$allDelivered && !$isEntregada)
                <i class="bi bi-info-circle me-1"></i>Completa la entrega de todos los items para habilitar el cobro.
                @elseif(!$isEntregada && !$allDelivered)
                <i class="bi bi-info-circle me-1"></i>Faltan items por entregar.
                @elseif(!$canCollect && !$allDelivered)
                <i class="bi bi-info-circle me-1"></i>Esperando entrega.
                @else
                <i class="bi bi-check-circle me-1 text-success"></i>Comanda lista para cobrar.
                @endif
            </div>
            <div class="d-inline-flex gap-2">
                @if($canEdit)
                <button type="button" class="btn btn-outline-brand" @click="refreshEditCart()" data-bs-toggle="modal" data-bs-target="#editModal">
                    <i class="bi bi-pencil me-1"></i> Editar
                </button>
                @endif
                @if($canCollect)
                <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#collectModal">
                    <i class="bi bi-cash-coin me-1"></i> Cobrar comanda
                </button>
                @endif
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-check-circle-fill me-1"></i>
        <span>Comanda cobrada.</span>
        @if($comanda->sale)
        <a href="{{ route('sales.show', $comanda->sale) }}" class="ms-auto">Ver venta #{{ $comanda->sale->id }}</a>
        @endif
    </div>
    @endif

    {{-- COLLECT MODAL --}}
    @if($canCollect && !$isCobrada)
    <div class="modal fade" id="collectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('comandas.collect', $comanda) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-cash-coin me-1"></i> Cobrar comanda #{{ $comanda->comanda_number }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div class="text-muted small">Total a cobrar</div>
                            <h3 class="mb-0">Bs {{ number_format($comanda->total_bs, 2, ',', '.') }}</h3>
                            <small class="text-muted">≈ ${{ number_format($comanda->total_usd, 2, ',', '.') }} USD</small>
                        </div>

                        <label class="form-label mb-2">Método de pago</label>
                        <div class="row g-2 mb-3">
                            @php($methods = ['efectivo' => ['Efectivo', 'bi-cash'], 'biopago' => ['Biopago', 'bi-qr-code'], 'pago_movil' => ['Pago Móvil', 'bi-phone'], 'pdv' => ['PDV', 'bi-credit-card-2-front'], 'credito' => ['Crédito', 'bi-journal-bookmark']])
                            @foreach($methods as $value => [$label, $icon])
                            <div class="col-6">
                                <label class="payment-option d-flex align-items-center gap-2" :class="{ 'selected': paymentMethod === '{{ $value }}' }">
                                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="paymentMethod">
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
                        <button type="submit" class="btn btn-brand">Confirmar cobro</button>
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
                            <div class="col-4">
                                <label class="form-label mb-1">Tipo</label>
                                <select name="order_type" class="form-select form-select-sm" x-model="orderType">
                                    <option value="local">Consumo local</option>
                                    <option value="para_llevar">Para llevar</option>
                                    <option value="delivery">Delivery</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label mb-1">Nombre</label>
                                <input type="text" name="customer_name" class="form-control form-control-sm" x-model="customerName" placeholder="Delivery">
                            </div>
                            <div class="col-4">
                                <label class="form-label mb-1">Nota cocina</label>
                                <input type="text" name="notes" class="form-control form-control-sm" x-model="notes">
                            </div>
                        </div>

                        <div class="mb-2 d-flex align-items-center gap-2">
                            <input type="search" class="form-control" placeholder="Buscar producto para agregar..." x-model="editSearch">
                            <span class="text-muted small" x-text="Object.keys(editCart).length + ' item(s)'"></span>
                        </div>

                        <div class="table-responsive mb-3" style="max-height:180px;overflow:auto;">
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
                                            </td>
                                            <td class="text-end" style="width:90px;">
                                                <button type="button" class="btn btn-sm btn-brand" @click="editCart[product.id] = (editCart[product.id] || 0) + 1">
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
                                    <tr><th>Item</th><th class="text-center">Cant</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <template x-for="item in editItems" :key="item.product_id">
                                        <tr>
                                            <td>
                                                <input type="hidden" :name="'cart[' + item.key + '][product_id]'" :value="item.product_id">
                                                <input type="hidden" :name="'cart[' + item.key + '][quantity]'" :value="item.quantity">
                                                <span x-text="item.name"></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <button type="button" class="qty-btn" @click="decEditQty(item.product_id)"><i class="bi bi-dash"></i></button>
                                                    <input type="number" class="qty-input" style="width:50px;" :value="item.quantity" min="1" readonly>
                                                    <button type="button" class="qty-btn" @click="encEditQty(item.product_id)"><i class="bi bi-plus"></i></button>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="remove-btn" @click="delete editCart[item.product_id]" aria-label="Quitar"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-brand" :disabled="Object.keys(editCart).length === 0">Guardar cambios</button>
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
            orderType: @json($comanda->order_type),
            customerName: @json($comanda->customer_name),
            notes: @json($comanda->notes),
            editSearch: '',
            editCart: {},

            get products() {
                return [
                    @foreach($products as $p)
                        { id: {{ $p->id }}, name: {!! json_encode($p->name) !!}, sale_price: {{ number_format($p->sale_price * $rate, 2, '.', '') }}, category_id: {{ $p->category_id ?? 'null' }}, image: {!! json_encode($p->image ? asset('storage/'.$p->image) : '') !!} },
                    @endforeach
                ];
            },
            get combos() {
                return [
                    @foreach($combos as $combo)
                        { id: 'combo_{{ $combo->id }}', name: {!! json_encode($combo->name) !!}, is_combo: true },
                    @endforeach
                ];
            },
            get allItems() {
                return [...this.products, ...this.combos];
            },
            get editFiltered() {
                return this.allItems.filter(p => this.editSearch === '' || p.name.toLowerCase().includes(this.editSearch.toLowerCase()));
            },
            get editItems() {
                return Object.entries(this.editCart).map(([key, quantity], idx) => {
                    let product = this.allItems.find(p => String(p.id) === String(key));
                    return { key: idx, product_id: String(key), name: product ? product.name : key, quantity: quantity };
                });
            },

            refreshEditCart() {
                this.editCart = {};
                @foreach($comanda->items as $item)
                    this.editCart[{{ $item->combo_id ? "'combo_".$item->combo_id : $item->product_id }}] = {{ $item->quantity }};
                @endforeach
            },
            encEditQty(id) { this.editCart[id]++; },
            decEditQty(id) {
                if (this.editCart[id] > 1) { this.editCart[id]--; } else { delete this.editCart[id]; }
            },
        };
    });
});
</script>
@endpush
