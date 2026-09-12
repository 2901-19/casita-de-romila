<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\Comanda;
use App\Models\Combo;
use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\Pricing;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * Ejecuta un checkout reutilizable (POS y comandas).
     *
     * El precio lo recalcula el servidor desde la BD (sale_price × tasa
     * vigente) y NUNCA confía en el precio enviado por el cliente.
     *
     * @param  array  $cart  items: [{product_id, name?, price?, quantity}]
     * @param  string  $paymentMethod  efectivo|biopago|pago_movil|pdv|credito
     * @param  int|null  $comandaId  comanda que genera esta venta (opcional)
     *
     * @throws CheckoutException
     */
    public function execute(
        array $cart,
        string $paymentMethod,
        ?int $customerId,
        int $userId,
        ?int $comandaId = null,
    ): Sale {
        $rate = $this->currentRate();
        $cart = $this->normalizeCart($cart, $rate);
        $total = round(array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0), 2);
        $isCredit = $paymentMethod === 'credito';

        $customer = $this->resolveCustomer($isCredit, $customerId, $total);

        return DB::transaction(function () use ($cart, $total, $rate, $paymentMethod, $isCredit, $customer, $userId, $comandaId) {
            $this->serializeNumbering('sale_number');
            $saleNumber = $this->nextSaleNumber();

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'user_id' => $userId,
                'customer_id' => $isCredit ? $customer->id : null,
                'customer_name' => $isCredit ? $customer->name : null,
                'total' => $total,
                'status' => $isCredit ? 'pendiente' : 'completada',
                'payment_method' => $isCredit ? 'credito' : null,
                'rate' => $rate,
                'paid_at' => $isCredit ? null : now(),
            ]);

            $this->createSaleItems($sale, $cart);

            if ($isCredit) {
                $this->createCreditCharge($sale, $customer, $total, $userId);
            } else {
                $sale->payments()->create([
                    'method' => $paymentMethod,
                    'amount' => $total,
                ]);
            }

            if ($comandaId) {
                Comanda::whereKey($comandaId)->update([
                    'sale_id' => $sale->id,
                    'status' => Comanda::STATUS_COBRADA,
                ]);
            }

            return $sale;
        });
    }

    /**
     * Cierra una comanda ya cobrada por completo, generando la Sale definitiva.
     * Las comanda_payments se agrupan por método (una SalePayment por método).
     * Si todos los pagos son crédito, la venta queda 'pendiente' con cargo a crédito.
     * Los precios de la comanda (Bs congelados) se conservan tal cual.
     *
     * @throws CheckoutException
     */
    public function closeComanda(Comanda $comanda, int $userId): Sale
    {
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            throw new CheckoutException('La comanda ya está cerrada.');
        }

        if (! $comanda->isFullyCollected()) {
            throw new CheckoutException('La comanda no está cobrada en su totalidad.');
        }

        $cart = $comanda->items->map(fn ($item) => [
            'product_id' => $item->combo_id ? "combo_{$item->combo_id}" : $item->product_id,
            'name' => $item->product_name,
            'price' => (float) $item->unit_price,
            'quantity' => (int) $item->quantity,
        ])->values()->all();

        if (empty($cart)) {
            throw new CheckoutException('La comanda no tiene productos para cerrar.');
        }

        $total = round((float) $comanda->items->sum('subtotal'), 2);

        $rate = $this->currentRate();

        $payments = $comanda->payments;
        $isCredit = $payments->isNotEmpty() && $payments->every(fn ($p) => $p->method === 'credito');
        $customer = null;

        if ($isCredit) {
            $creditCustomers = $payments->where('method', 'credito')->pluck('customer_id')->filter()->unique()->values();
            if ($creditCustomers->count() > 1) {
                throw new CheckoutException('La comanda tiene créditos asignados a más de un cliente.');
            }
            $customer = $this->resolveCustomer(true, (int) $creditCustomers->first(), $total);
        }

        return DB::transaction(function () use ($cart, $total, $rate, $isCredit, $customer, $userId, $payments, $comanda) {
            $this->serializeNumbering('sale_number');
            $this->lockAndValidateStock($cart);
            $saleNumber = $this->nextSaleNumber();

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'user_id' => $userId,
                'customer_id' => $isCredit ? $customer->id : null,
                'customer_name' => $isCredit ? $customer->name : null,
                'total' => $total,
                'status' => $isCredit ? 'pendiente' : 'completada',
                'payment_method' => $isCredit ? 'credito' : null,
                'rate' => $rate,
                'paid_at' => $isCredit ? null : now(),
            ]);

            $this->createSaleItems($sale, $cart);

            if ($isCredit) {
                $this->createCreditCharge($sale, $customer, $total, $userId);
            } else {
                foreach ($payments->groupBy('method') as $method => $group) {
                    $sale->payments()->create([
                        'method' => $method,
                        'amount' => round($group->sum('amount'), 2),
                    ]);
                }
            }

            $comanda->update([
                'sale_id' => $sale->id,
                'status' => Comanda::STATUS_COBRADA,
            ]);

            return $sale;
        });
    }

    /**
     * Recalcula el carrito con precios y nombres autoritarios desde la BD.
     * Lanza CheckoutException si un producto/combo no existe o está inactivo.
     */
    protected function normalizeCart(array $cart, float $rate): array
    {
        if (empty($cart)) {
            throw new CheckoutException('El carrito está vacío.');
        }

        $normalized = [];

        foreach ($cart as $item) {
            $itemId = $item['product_id'];
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($quantity < 1) {
                throw new CheckoutException('Cantidad inválida en el carrito.');
            }

            if (str_starts_with((string) $itemId, 'combo_')) {
                $combo = Combo::query()->with('products')->find((int) substr((string) $itemId, 6));
                if (! $combo || ! $combo->is_active) {
                    throw new CheckoutException('El combo seleccionado no existe o no está activo.');
                }
                $normalized[] = [
                    'product_id' => "combo_{$combo->id}",
                    'name' => $combo->name,
                    'price' => Pricing::bs((float) $combo->sale_price, $rate, $combo->round_bs),
                    'quantity' => $quantity,
                ];
            } else {
                $product = Product::find((int) $itemId);
                if (! $product || ! $product->is_active) {
                    throw new CheckoutException('El producto seleccionado no existe o no está activo.');
                }
                $normalized[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => Pricing::bs((float) $product->sale_price, $rate, $product->round_bs),
                    'quantity' => $quantity,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Valida stock dentro de la transacción con locks de escritura sobre las
     * filas de productos/combos para evitar sobreventa entre requests
     * concurrentes. Debe ejecutarse dentro de DB::transaction.
     */
    protected function lockAndValidateStock(array $cart): void
    {
        foreach ($cart as $item) {
            $itemId = $item['product_id'];
            $quantity = (int) $item['quantity'];

            if ((string) $itemId === '') {
                continue;
            }

            if (str_starts_with((string) $itemId, 'combo_')) {
                $comboId = (int) substr((string) $itemId, 6);
                $combo = Combo::with('inventariableComponents')->lockForUpdate()->find($comboId);
                if (! $combo) {
                    throw new CheckoutException('El combo seleccionado no existe.');
                }

                $components = $combo->inventariableComponents->keyBy('id');
                $locked = Product::whereKey($components->keys())->lockForUpdate()->get()->keyBy('id');

                foreach ($components as $component) {
                    $qtyNeeded = $component->pivot->quantity * $quantity;
                    $stock = (int) ($locked->get($component->id)->stock_current ?? 0);
                    if ($stock < $qtyNeeded) {
                        throw new CheckoutException(
                            "Stock insuficiente para '{$component->name}' (componente de {$combo->name}). Disponible: {$stock}, Necesario: {$qtyNeeded}"
                        );
                    }
                }
            } else {
                $product = Product::whereKey($itemId)->lockForUpdate()->first();
                if (! $product) {
                    throw new CheckoutException('El producto seleccionado no existe.');
                }
                if ($product->control_type === 'demanda') {
                    continue;
                }
                if ($product->stock_current < $quantity) {
                    throw new CheckoutException(
                        "Stock insuficiente para '{$product->name}'. Disponible: {$product->stock_current}"
                    );
                }
            }
        }
    }

    protected function resolveCustomer(bool $isCredit, ?int $customerId, float $total): ?Customer
    {
        if (! $isCredit) {
            return null;
        }

        $customer = Customer::find($customerId);
        if (! $customer) {
            throw new CheckoutException('Cliente no encontrado.');
        }
        if (! $customer->is_active) {
            throw new CheckoutException('El cliente seleccionado no es válido.');
        }

        $rate = $this->currentRate();
        $totalUsd = round($total / $rate, 2);
        if ($customer->hasDefinedLimit() && $totalUsd > $customer->availableCredit()) {
            throw new CheckoutException(sprintf(
                'Límite de crédito excedido para %s. Disponible: $%s USD. Esta venta requiere $%s USD.',
                $customer->name,
                number_format($customer->availableCredit(), 2, ',', '.'),
                number_format($totalUsd, 2, ',', '.')
            ));
        }

        return $customer;
    }

    protected function createSaleItems(Sale $sale, array $cart): void
    {
        foreach ($cart as $item) {
            $itemId = $item['product_id'];
            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $item['price'];
            $subtotal = round($unitPrice * $quantity, 2);

            if (str_starts_with((string) $itemId, 'combo_')) {
                $comboId = (int) substr((string) $itemId, 6);
                $combo = Combo::with('inventariableComponents')->find($comboId);
                $sale->items()->create([
                    'product_id' => null,
                    'combo_id' => $comboId,
                    'product_name' => $item['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
                foreach ($combo->inventariableComponents ?? [] as $component) {
                    $component->decrement('stock_current', $component->pivot->quantity * $quantity);
                }
            } else {
                $sale->items()->create([
                    'product_id' => $itemId,
                    'combo_id' => null,
                    'product_name' => $item['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
                $product = Product::find($itemId);
                if ($product && in_array($product->control_type, ['inventariable', 'produccion'])) {
                    $product->decrement('stock_current', $quantity);
                }
            }
        }
    }

    protected function createCreditCharge(Sale $sale, Customer $customer, float $total, int $userId): void
    {
        $rate = $this->currentRate();
        $amountUsd = round($total / $rate, 2);

        CreditMovement::create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'user_id' => $userId,
            'type' => 'cargo',
            'amount' => $amountUsd,
            'notes' => "Venta a crédito #{$sale->id}",
            'rate' => $rate,
        ]);

        $customer->decrement('balance', $amountUsd);
    }

    protected function currentRate(): float
    {
        $rate = (float) (ExchangeRate::latest()->first()->rate ?? 0);

        if ($rate <= 0) {
            throw new CheckoutException('No hay una tasa de cambio registrada. Registre la tasa del día antes de procesar ventas.');
        }

        return $rate;
    }

    /**
     * Serializa la generación de números correlativos vía advisory lock de
     * PostgreSQL (no-op en SQLite, donde los tests corren en un solo proceso).
     */
    protected function serializeNumbering(string $scope): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::select('select pg_advisory_xact_lock(?)', [crc32($scope)]);
    }

    protected function nextSaleNumber(): string
    {
        $last = (int) Sale::max('id');

        return str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }
}
