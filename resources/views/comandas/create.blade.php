@extends('layouts.app')

@section('title', 'Nueva Comanda')

@section('topbar-actions')
<a href="{{ route('comandas.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Volver
</a>
@endsection

@section('content')
<div class="pos-layout" x-data="comandaApp()">

    {{-- Left: Products --}}
    <div class="pos-products">
        <div class="pos-categories">
            <button class="cat-btn" :class="{ 'active': selectedCategory === 'all' }" @click="selectedCategory = 'all'">
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

        <div class="pos-toolbar d-flex align-items-center gap-2 mb-2">
            <div class="pos-search flex-grow-1 mb-0">
                <i class="bi bi-search search-icon-pos" aria-hidden="true"></i>
                <input type="search" class="form-control" placeholder="Buscar producto..." x-model="searchQuery">
            </div>
            <span class="text-muted" style="font-size:0.8rem;" x-text="Object.keys(cart).length + ' item(s)'"></span>
        </div>

        <div class="table-responsive flex-grow-1 overflow-auto">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:48px;"></th>
                        <th>Producto</th>
                        <th class="text-end">Precio</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="product in filteredProducts" :key="product.id">
                        <tr>
                            <td>
                                <template x-if="product.image">
                                    <img :src="product.image" alt="" class="pos-thumb" loading="lazy">
                                </template>
                                <template x-if="!product.image">
                                    <span class="pos-thumb pos-thumb-placeholder"><i class="bi bi-box"></i></span>
                                </template>
                            </td>
                            <td>
                                <span x-text="product.name"></span>
                                <template x-if="product.is_combo">
                                    <span class="badge-soft success ms-1">Combo</span>
                                </template>
                            </td>
                            <td class="text-end">Bs <span x-text="formatNumber(product.sale_price)"></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-brand" @click="addToCart(product)" aria-label="Agregar">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredProducts.length === 0">
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="bi bi-search d-block" style="font-size:1.5rem;opacity:0.4;"></i>
                            No hay productos que mostrar
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Right: Order form --}}
    <div class="pos-cart">
        <form method="POST" action="{{ route('comandas.store') }}">
            @csrf
            <div class="pos-cart-header">
                <h2 class="pos-cart-title"><i class="bi bi-journal-text me-1"></i> Comanda</h2>
            </div>

            <div class="pos-cart-items">
                <template x-for="item in cartItems" :key="item.product_id">
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <span class="cart-item-name" x-text="item.name"></span>
                            <span class="cart-item-unit" x-text="'Bs ' + formatNumber(item.sale_price) + ' c/u'"></span>
                        </div>
                        <div class="cart-item-controls">
                            <input type="hidden" :name="'cart[' + item.key + '][product_id]'" :value="item.product_id">
                            <input type="hidden" :name="'cart[' + item.key + '][quantity]'" :value="item.quantity">
                            <button type="button" class="qty-btn" @click="decreaseQty(item.product_id)" aria-label="Reducir">
                                <i class="bi bi-dash"></i>
                            </button>
                            <input type="number" class="qty-input" :value="item.quantity" min="1" readonly aria-label="Cantidad">
                            <button type="button" class="qty-btn" @click="increaseQty(item.product_id)" aria-label="Aumentar">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        <div class="cart-item-subtotal">
                            <span x-text="'Bs ' + formatNumber(item.sale_price * item.quantity)"></span>
                            <button type="button" class="remove-btn" @click="removeFromCart(item.product_id)" aria-label="Quitar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </template>

                <div x-show="Object.keys(cart).length === 0" class="pos-cart-empty">
                    <i class="bi bi-journal-text"></i>
                    <p>Comanda vacía</p>
                    <small>Agrega productos al pedido</small>
                </div>
            </div>

            <div class="pos-cart-footer" x-show="Object.keys(cart).length > 0" x-cloak>
                <div class="pos-totals">
                    <div class="total-row total-final">
                        <span>Total</span>
                        <span x-text="'Bs ' + formatNumber(subtotal)"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label mb-1"><i class="bi bi-bag me-1"></i> Tipo de pedido</label>
                    <select name="order_type" class="form-select form-select-sm">
                        <option value="local">Consumo local</option>
                        <option value="para_llevar">Para llevar</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="comandaCustomer" class="form-label mb-1">
                        <i class="bi bi-person-badge me-1"></i> Nombre (delivery)
                    </label>
                    <input id="comandaCustomer" type="text" name="customer_name" class="form-control form-control-sm" placeholder="Opcional">
                </div>

                <div class="mb-3">
                    <label for="comandaNotes" class="form-label mb-1">
                        <i class="bi bi-chat-left-text me-1"></i> Nota para cocina
                    </label>
                    <input id="comandaNotes" type="text" name="notes" class="form-control form-control-sm" placeholder="Opcional">
                </div>

                <button type="submit" class="btn btn-brand w-100" :disabled="Object.keys(cart).length === 0">
                    <i class="bi bi-check2-circle me-1"></i> Registrar comanda
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', function () {
    Alpine.data('comandaApp', function () {
        return {
            cart: {},
            selectedCategory: 'all',
            searchQuery: '',

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
                        {
                            id: 'combo_{{ $combo->id }}',
                            name: {!! json_encode($combo->name) !!},
                            sale_price: {{ number_format($combo->sale_price * $rate, 2, '.', '') }},
                            is_combo: true,
                        },
                    @endforeach
                ];
            },

            get allItems() {
                return [...this.products, ...this.combos];
            },

            get filteredProducts() {
                return this.allItems.filter(p => {
                    let matchCat = this.selectedCategory === 'all' || p.category_id === this.selectedCategory;
                    let matchSearch = this.searchQuery === '' || p.name.toLowerCase().includes(this.searchQuery.toLowerCase());
                    return matchCat && matchSearch;
                });
            },

            get cartItems() {
                return Object.entries(this.cart).map(([key, quantity], idx) => {
                    let product = this.allItems.find(p => String(p.id) === String(key));
                    return {
                        key: idx,
                        product_id: String(key),
                        name: product ? product.name : key,
                        sale_price: product ? product.sale_price : 0,
                        quantity: quantity,
                    };
                });
            },

            get subtotal() {
                return this.cartItems.reduce((sum, item) => sum + item.sale_price * item.quantity, 0);
            },

            addToCart(product) {
                const key = String(product.id);
                this.cart[key] = (this.cart[key] || 0) + 1;
            },
            increaseQty(key) { this.cart[String(key)]++; },
            decreaseQty(key) {
                const k = String(key);
                if (this.cart[k] > 1) { this.cart[k]--; } else { this.removeFromCart(k); }
            },
            removeFromCart(key) { delete this.cart[String(key)] },

            formatNumber(n) {
                return n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    });
});
</script>
@endpush
