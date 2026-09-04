@extends('layouts.app')

@section('title', 'POS / Ventas')

@section('page-shell', 'panel')

@section('topbar-actions')
<div class="d-flex align-items-center gap-2">
    <span class="text-muted" style="font-size:0.85rem;">
        <i class="bi bi-clock me-1"></i>
        {{ now()->format('d M Y, h:i a') }}
    </span>
    <button class="btn btn-outline-secondary btn-sm" type="button" @click="$dispatch('pos-clear-cart')">
        <i class="bi bi-x-circle me-1"></i> Vaciar carrito
    </button>
</div>
@endsection

@section('content')
<div class="panel-root">
<div class="pos-layout" x-data="posApp()" @pos-clear-cart.window="clearCart()">

    {{-- Left: Categories + Products --}}
    <div class="pos-products">

        {{-- Category filters --}}
        <div class="pos-categories">
            <button class="cat-btn"
                    :class="{ 'active': selectedCategory === 'all' }"
                    @click="selectedCategory = 'all'">
                Todos
            </button>
            @foreach($categories as $category)
                <button class="cat-btn"
                        :class="{ 'active': selectedCategory === {{ $category->id }} }"
                        @click="selectedCategory = {{ $category->id }}">
                    {{ $category->name }}
                </button>
            @endforeach
        </div>

        {{-- Search + Pager toolbar --}}
        <div class="pos-toolbar d-flex align-items-center gap-2 mb-2">
            <div class="pos-search flex-grow-1 mb-0">
                <i class="bi bi-search search-icon-pos" aria-hidden="true"></i>
                <input type="search"
                       class="form-control"
                       placeholder="Buscar producto..."
                       x-model="searchQuery"
                       aria-label="Buscar producto">
            </div>
            <nav class="pos-pager mb-0 mt-0" x-show="totalPages > 1" aria-label="Paginación de productos" style="flex-shrink:0;">
                <button type="button" class="cat-btn" @click="prevPage()" :disabled="page === 1" aria-label="Página anterior">&lsaquo;</button>
                <span x-text="page + ' / ' + totalPages"></span>
                <button type="button" class="cat-btn" @click="nextPage()" :disabled="page >= totalPages" aria-label="Página siguiente">&rsaquo;</button>
            </nav>
        </div>

        {{-- Products grid --}}
        <div class="pos-grid-wrap flex-grow-1 overflow-auto">
            <div class="pos-grid">
                <template x-if="loading">
                    <div class="text-center text-muted py-4 col-span-3">
                        <div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Cargando</span></div>
                        Cargando productos...
                    </div>
                </template>
                <template x-for="product in paginatedProducts" :key="product.id">
                    <button type="button" class="product-card"
                            @click="addToCart(product)"
                            :aria-label="'Agregar ' + product.name + ' al carrito'">
                        <span class="product-card-thumb" aria-hidden="true">
                            <template x-if="product.image">
                                <img :src="product.image" alt="" class="pos-thumb" loading="lazy">
                            </template>
                            <template x-if="!product.image">
                                <i class="bi bi-box"></i>
                            </template>
                        </span>
                        <span class="product-card-info">
                            <span class="product-card-name">
                                <span x-text="product.name"></span>
                                <template x-if="product.is_combo">
                                    <span class="badge-soft success ms-1">Combo</span>
                                </template>
                            </span>
                            <span class="product-card-price">
                                Bs <span x-text="formatNumber(product.sale_price)"></span>
                            </span>
                        </span>
                    </button>
                </template>
                <template x-if="!loading && filteredProducts.length === 0">
                    <div class="pos-empty col-span-3">
                        <i class="bi bi-search"></i>
                        <p>No hay productos que mostrar</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Right: Cart --}}
    <div class="pos-cart">
        <div class="pos-cart-header">
            <h2 class="pos-cart-title">
                <i class="bi bi-cart4 me-1"></i> Carrito
            </h2>
            <span class="pos-cart-count badge-brand" x-text="Object.keys(cart).length + ' producto' + (Object.keys(cart).length !== 1 ? 's' : '')" x-show="Object.keys(cart).length > 0"></span>
        </div>

        <div class="pos-cart-items">
            <template x-for="(item, id) in cart" :key="id">
                <div class="cart-item">
                    <div class="cart-item-info">
                        <span class="cart-item-name" x-text="item.name"></span>
                        <span class="cart-item-unit" x-text="'Bs ' + formatNumber(item.price) + ' c/u'"></span>
                        <template x-if="item.is_combo && item.components">
                            <small class="text-muted d-block" style="font-size:0.7rem;" x-text="item.components.map(c => c.name + ' x' + c.quantity).join(', ')"></small>
                        </template>
                    </div>
                    <div class="cart-item-controls">
                        <button class="qty-btn" @click="decreaseQty(id)" :disabled="item.quantity <= 1" aria-label="Reducir cantidad">
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number"
                               class="qty-input"
                               :value="item.quantity"
                               min="1"
                               readonly
                               aria-label="Cantidad">
                        <button class="qty-btn" @click="increaseQty(id)" aria-label="Aumentar cantidad">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                    <div class="cart-item-subtotal">
                        <span x-text="'Bs ' + formatNumber(item.price * item.quantity)"></span>
                        <button class="remove-btn" @click="removeFromCart(id)" aria-label="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </template>

            <div x-show="Object.keys(cart).length === 0" class="pos-cart-empty">
                <i class="bi bi-cart-x"></i>
                <p>Carrito vacío</p>
                <small>Haz clic en un producto para agregarlo</small>
            </div>
        </div>

        <div class="pos-cart-footer" x-show="Object.keys(cart).length > 0">
            <div class="pos-totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span x-text="'Bs ' + formatNumber(subtotal)"></span>
                </div>
                <div class="total-row total-final">
                    <span>Total</span>
                    <span x-text="'Bs ' + formatNumber(subtotal)"></span>
                </div>
            </div>
            <div class="pos-payment-methods">
                <label class="payment-option" :class="{ 'selected': paymentMethod === 'efectivo' }">
                    <input type="radio" name="cart_payment_method" value="efectivo" x-model="paymentMethod">
                    <i class="bi bi-cash"></i> Efectivo
                </label>
                <label class="payment-option" :class="{ 'selected': paymentMethod === 'biopago' }">
                    <input type="radio" name="cart_payment_method" value="biopago" x-model="paymentMethod">
                    <i class="bi bi-qr-code"></i> Biopago
                </label>
                <label class="payment-option" :class="{ 'selected': paymentMethod === 'pago_movil' }">
                    <input type="radio" name="cart_payment_method" value="pago_movil" x-model="paymentMethod">
                    <i class="bi bi-phone"></i> Pago Móvil
                </label>
                <label class="payment-option" :class="{ 'selected': paymentMethod === 'pdv' }">
                    <input type="radio" name="cart_payment_method" value="pdv" x-model="paymentMethod">
                    <i class="bi bi-credit-card-2-front"></i> PDV
                </label>
                <label class="payment-option" :class="{ 'selected': paymentMethod === 'credito' }">
                    <input type="radio" name="cart_payment_method" value="credito" x-model="paymentMethod">
                    <i class="bi bi-journal-bookmark"></i> Crédito
                </label>
            </div>

            <div class="pos-customer-select" x-show="paymentMethod === 'credito'" x-cloak>
                <label for="posCustomer" class="form-label mb-1">
                    <i class="bi bi-person-badge me-1"></i> Cliente
                </label>
                <select id="posCustomer" class="form-select form-select-sm" x-model="customerId">
                    <option value="">Seleccione un cliente...</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>

            <button class="btn btn-brand w-100"
                    @click="updateReceiptTime(); (checkoutModalInstance = checkoutModalInstance || new bootstrap.Modal(document.getElementById('checkoutModal'))).show()"
                    :disabled="processing || Object.keys(cart).length === 0 || (paymentMethod === 'credito' && !customerId)">
                <i class="bi bi-credit-card me-1"></i> <span x-show="processing">Procesando...</span><span x-show="!processing">Cobrar</span>
            </button>
        </div>
    </div>

    {{-- Modals (position:fixed — inside x-data scope for Alpine bindings) --}}
    <form method="POST" @submit.prevent="processCheckout">
        @csrf
        <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true" x-cloak>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-house-heart-fill me-1"></i> Casita de Romila
                            <small class="receipt-datetime text-muted d-block" x-text="currentTime"></small>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="checkout-receipt">
                            <div x-show="checkoutError" x-cloak class="alert alert-danger py-2 px-3 mb-3" role="alert" aria-live="assertive">
                                <i class="bi bi-exclamation-triangle me-1"></i><span x-text="checkoutError"></span>
                            </div>
                            <div class="receipt-items">
                                <div class="receipt-row receipt-row-head px-3 py-2">
                                    <span>Producto</span>
                                    <span>Cant</span>
                                    <span class="text-end">Total</span>
                                </div>
                                <template x-for="item in cartItems" :key="item.product_id">
                                    <div class="receipt-row px-3 py-2">
                                        <span x-text="item.name" style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></span>
                                        <span class="text-center" x-text="'x' + item.quantity"></span>
                                        <span class="text-end" x-text="'Bs ' + formatNumber(item.price * item.quantity)"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="receipt-totals px-3 py-2 border-top flex-shrink-0">
                                <div class="receipt-total-final">
                                    <span>Total</span>
                                    <strong x-text="'Bs ' + formatNumber(subtotal)"></strong>
                                </div>
                                <div class="receipt-meta">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Método</span>
                                        <span class="badge-soft success" x-text="paymentMethodLabel"></span>
                                    </div>
                                    <div class="d-flex justify-content-between" x-show="paymentMethod === 'credito'" x-cloak>
                                        <span class="text-muted">Cliente</span>
                                        <strong x-text="selectedCustomerName || '—'"></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="receipt-footer text-center py-2 px-3 border-top flex-shrink-0">
                                <small class="text-muted">¡Gracias por su compra!</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="processing">Cancelar</button>
                        <button type="submit" class="btn btn-brand" :disabled="processing">
                            <span x-show="processing" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            <span x-text="processing ? 'Procesando...' : 'Confirmar venta'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body pt-5 pb-4">
                    <div class="success-icon" aria-hidden="true">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h4 class="mb-2" x-text="lastSalePaymentMethod === 'credito' ? 'Crédito registrado' : '¡Venta exitosa!'"></h4>
                    <p class="text-muted mb-1" x-text="lastSalePaymentMethod === 'credito' ? ('Venta a crédito de ' + (selectedCustomerName || '') + '. Queda pendiente de pago.') : 'Venta procesada correctamente.'"></p>
                    <p class="mb-0">
                        <strong x-text="'Bs ' + formatNumber(lastSaleTotal)"></strong> —
                        <span x-text="methodLabel(lastSalePaymentMethod)"></span>
                    </p>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0">
                    <button type="button" class="btn btn-brand" data-bs-dismiss="modal" @click="clearCart()">
                        Nueva venta
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/pos.js'])
@endpush
