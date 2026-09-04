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
            <span class="text-muted" style="font-size:0.8rem;" x-text="lines.length + ' item(s)'"></span>
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
                                <template x-if="product.is_demanda">
                                    <span class="badge-soft info ms-1">Demanda</span>
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
                <template x-for="item in lines" :key="item.key">
                    <div class="cart-item cart-item-line">
                        <div class="cart-item-info">
                            <span class="cart-item-name" x-text="item.name"></span>
                            <span class="cart-item-unit" x-text="'Bs ' + formatNumber(item.sale_price) + ' c/u'"></span>
                        </div>
                        <div class="cart-item-controls">
                            <input type="hidden" :name="'cart[' + item.key + '][product_id]'" :value="item.product_id">
                            <input type="hidden" :name="'cart[' + item.key + '][quantity]'" :value="item.quantity">
                            <input type="hidden" :name="'cart[' + item.key + '][order_type]'" :value="item.order_type">
                            <input type="hidden" :name="'cart[' + item.key + '][note]'" :value="item.note">
                            <template x-if="!item.is_demanda">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" class="qty-btn" @click="decreaseQty(item.key)" aria-label="Reducir">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" class="qty-input" :value="item.quantity" min="1" readonly aria-label="Cantidad">
                                    <button type="button" class="qty-btn" @click="increaseQty(item.key)" aria-label="Aumentar">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </template>
                            <template x-if="item.is_demanda">
                                <span class="badge-soft info">1 ud</span>
                            </template>
                        </div>
                        <div class="cart-item-subtotal">
                            <span x-text="'Bs ' + formatNumber(item.sale_price * item.quantity)"></span>
                            <button type="button" class="remove-btn" @click="removeLine(item.key)" aria-label="Quitar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="cart-item-options w-100">
                            <select class="form-select form-select-sm" x-model="item.order_type">
                                <option value="local">Consumo local</option>
                                <option value="para_llevar">Para llevar</option>
                                <option value="delivery">Delivery</option>
                            </select>
                            <input type="text" class="form-control form-control-sm" x-model="item.note" placeholder="Nota (opcional)">
                        </div>
                    </div>
                </template>

                <div x-show="lines.length === 0" class="pos-cart-empty">
                    <i class="bi bi-journal-text"></i>
                    <p>Comanda vacía</p>
                    <small>Agrega productos al pedido</small>
                </div>
            </div>

            <div class="pos-cart-footer" x-show="lines.length > 0" x-cloak>
                <div class="pos-totals">
                    <div class="total-row total-final">
                        <span>Total</span>
                        <span x-text="'Bs ' + formatNumber(subtotal)"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label mb-1"><i class="bi bi-bag me-1"></i> Aplicar tipo a todos los items</label>
                    <select class="form-select form-select-sm" x-model="defaultOrderType" @change="applyOrderTypeToAll()">
                        <option value="local">Consumo local</option>
                        <option value="para_llevar">Para llevar</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>

                <div class="mb-3" x-show="hasDeliveryLine" x-cloak>
                    <label for="comandaCustomer" class="form-label mb-1">
                        <i class="bi bi-person-badge me-1"></i> Nombre (delivery)
                    </label>
                    <input id="comandaCustomer" type="text" name="customer_name" class="form-control form-control-sm" placeholder="Opcional">
                </div>

                <button type="submit" class="btn btn-brand w-100" :disabled="lines.length === 0">
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
            lines: [],
            nextKey: 0,
            selectedCategory: 'all',
            searchQuery: '',
            defaultOrderType: 'local',

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
                        {
                            id: 'combo_{{ $combo->id }}',
                            name: {!! json_encode($combo->name) !!},
                            sale_price: {{ number_format($combo->sale_price * $rate, 2, '.', '') }},
                            is_combo: true,
                            is_demanda: false,
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

            get subtotal() {
                return this.lines.reduce((sum, item) => sum + item.sale_price * item.quantity, 0);
            },

            get hasDeliveryLine() {
                return this.lines.some(l => l.order_type === 'delivery');
            },

            addToCart(product) {
                if (!product.is_demanda) {
                    let line = this.lines.find(l => String(l.product_id) === String(product.id) && !l.is_demanda);
                    if (line) {
                        line.quantity++;
                        return;
                    }
                }
                this.lines.push({
                    key: 'n' + (this.nextKey++),
                    product_id: product.id,
                    name: product.name,
                    sale_price: product.sale_price,
                    is_combo: product.is_combo || false,
                    is_demanda: product.is_demanda || false,
                    quantity: 1,
                    order_type: this.defaultOrderType,
                    note: '',
                });
            },
            increaseQty(key) {
                let line = this.lines.find(l => l.key === key);
                if (line) { line.quantity++; }
            },
            decreaseQty(key) {
                let idx = this.lines.findIndex(l => l.key === key);
                if (idx === -1) { return; }
                if (this.lines[idx].quantity > 1) { this.lines[idx].quantity--; } else { this.lines.splice(idx, 1); }
            },
            removeLine(key) {
                this.lines = this.lines.filter(l => l.key !== key);
            },
            applyOrderTypeToAll() {
                this.lines.forEach(l => { l.order_type = this.defaultOrderType; });
            },

            formatNumber(n) {
                return n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    });
});
</script>
@endpush