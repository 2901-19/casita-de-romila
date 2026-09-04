document.addEventListener('alpine:init', function () {
    Alpine.data('posApp', function () {
        return {
            cart: {},
            items: [],
            selectedCategory: 'all',
            searchQuery: '',
            page: 1,
            pageSize: 9,
            totalPages: 1,
            loading: false,
            paymentMethod: 'efectivo',
            customerId: '',
            lastSaleTotal: 0,
            lastSalePaymentMethod: '',
            _receiptTime: '',
            _debounceTimer: null,

            init: function () {
                this.cart = {};
                this.fetchProducts();

                this.$watch('searchQuery', function () {
                    clearTimeout(this._debounceTimer);
                    this._debounceTimer = setTimeout(() => {
                        this.page = 1;
                        this.fetchProducts();
                    }, 250);
                }.bind(this));

                this.$watch('selectedCategory', function () {
                    this.page = 1;
                    this.fetchProducts();
                }.bind(this));
            },

            get allItems() {
                return this.items;
            },

            get filteredProducts() {
                return this.items;
            },

            get paginatedProducts() {
                return this.items;
            },

            fetchProducts: async function () {
                if (this.loading) return;
                this.loading = true;
                try {
                    const params = new URLSearchParams({ page: this.page });
                    if (this.searchQuery) params.set('search', this.searchQuery);
                    if (this.selectedCategory !== 'all') params.set('category_id', this.selectedCategory);
                    const response = await fetch('/pos/products?' + params.toString(), {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    this.items = data.items || [];
                    this.totalPages = data.total_pages || 1;
                    this.page = data.page || 1;
                } finally {
                    this.loading = false;
                }
            },

            prevPage() {
                if (this.page > 1) {
                    this.page--;
                    this.fetchProducts();
                }
            },

            nextPage() {
                if (this.page < this.totalPages) {
                    this.page++;
                    this.fetchProducts();
                }
            },

            get subtotal() {
                return Object.values(this.cart).reduce((sum, item) => sum + (item.price * item.quantity), 0);
            },

            get cartItems() {
                return Object.values(this.cart);
            },

            updateReceiptTime() {
                this._receiptTime = new Date().toLocaleString('es-VE', {
                    day: '2-digit', month: 'short', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
            },

            get currentTime() {
                return this._receiptTime;
            },

            get paymentMethodLabel() {
                return this.methodLabel(this.paymentMethod);
            },

            get selectedCustomerName() {
                if (!this.customerId) return '';
                var option = document.querySelector('#posCustomer option[value="' + this.customerId + '"]');
                return option ? option.textContent.trim() : '';
            },

            methodLabel: function (method) {
                var labels = { efectivo: 'Efectivo', biopago: 'Biopago', pago_movil: 'Pago Móvil', pdv: 'PDV', credito: 'Crédito' };
                return labels[method] || method;
            },

            addToCart(product) {
                if (this.cart[product.id]) {
                    this.cart[product.id].quantity++;
                } else {
                    var cartItem = {
                        product_id: product.id,
                        name: product.name,
                        price: parseFloat(product.sale_price),
                        quantity: 1,
                    };
                    if (product.is_combo) {
                        cartItem.is_combo = true;
                        cartItem.components = product.components || [];
                    }
                    this.cart[product.id] = cartItem;
                }
            },

            removeFromCart(id) {
                delete this.cart[id];
            },

            increaseQty(id) {
                this.cart[id].quantity++;
            },

            decreaseQty(id) {
                if (this.cart[id].quantity > 1) {
                    this.cart[id].quantity--;
                } else {
                    this.removeFromCart(id);
                }
            },

            clearCart: function () {
                this.cart = {};
                this.paymentMethod = 'efectivo';
            },

            formatNumber: function (n) {
                return n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            processCheckout: async function () {
                let bootstrapModal = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
                bootstrapModal.hide();

                try {
                    let response = await fetch('/pos', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            cart: this.cart,
                            payment_method: this.paymentMethod,
                            customer_id: this.paymentMethod === 'credito' ? this.customerId : null,
                        }),
                    });

                    let data = await response.json();

                    if (response.ok) {
                        this.lastSaleTotal = this.subtotal;
                        this.lastSalePaymentMethod = this.paymentMethod;
                        this.clearCart();
                        let successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                    } else {
                        var errorMsg = data.error || data.message || 'Error al procesar la venta.';
                        if (data.errors) {
                            var firstError = Object.values(data.errors)[0];
                            if (Array.isArray(firstError)) {
                                errorMsg = firstError[0];
                            }
                        }
                        window.toast.fire({ icon: 'error', title: errorMsg });
                    }
                } catch (err) {
                    window.toast.fire({ icon: 'error', title: 'Error de conexión. Intenta de nuevo.' });
                }
            },
        };
    });
});
