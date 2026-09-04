<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\Comanda;
use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * Ejecuta un checkout reutilizable (POS y comandas).
     *
     * @param array $cart          items: [{product_id, name, price, quantity}]
     * @param string $paymentMethod efectivo|biopago|pago_movil|pdv|credito
     * @param int|null $customerId
     * @param int $userId
     * @param int|null $comandaId  comanda que genera esta venta (opcional)
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
        $total = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0);
        $isCredit = $paymentMethod === 'credito';

        $this->validateStock($cart);
        $customer = $this->resolveCustomer($isCredit, $customerId, $total);

        return DB::transaction(function () use ($cart, $total, $paymentMethod, $isCredit, $customer, $userId, $comandaId) {
            $saleNumber = $this->nextSaleNumber();

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'user_id' => $userId,
                'customer_id' => $isCredit ? $customer->id : null,
                'customer_name' => $isCredit ? $customer->name : null,
                'total' => $total,
                'status' => $isCredit ? 'pendiente' : 'completada',
                'payment_method' => $isCredit ? 'credito' : null,
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
     *
     * @throws CheckoutException
     */
    public function closeComanda(Comanda $comanda, int $userId): Sale
    {
        $cart = $comanda->items->map(fn ($item) => [
            'product_id' => $item->combo_id ? "combo_{$item->combo_id}" : $item->product_id,
            'name' => $item->product_name,
            'price' => (float) $item->unit_price,
            'quantity' => (int) $item->quantity,
        ])->values()->all();

        if (empty($cart)) {
            throw new CheckoutException('La comanda no tiene productos para cerrar.');
        }

        $total = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0);

        $this->validateStock($cart);

        $payments = $comanda->payments;
        $isCredit = $payments->isNotEmpty() && $payments->every(fn ($p) => $p->method === 'credito');
        $customer = null;

        if ($isCredit) {
            $creditPayment = $payments->first();
            $customer = $this->resolveCustomer(true, $creditPayment->customer_id, $total);
        }

        return DB::transaction(function () use ($cart, $total, $isCredit, $customer, $userId, $payments, $comanda) {
            $saleNumber = $this->nextSaleNumber();

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'user_id' => $userId,
                'customer_id' => $isCredit ? $customer->id : null,
                'customer_name' => $isCredit ? $customer->name : null,
                'total' => $total,
                'status' => $isCredit ? 'pendiente' : 'completada',
                'payment_method' => $isCredit ? 'credito' : null,
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

    protected function validateStock(array $cart): void
    {
        foreach ($cart as $item) {
            $itemId = $item['product_id'];

            if (str_starts_with((string) $itemId, 'combo_')) {
                $comboId = (int) substr((string) $itemId, 6);
                $combo = \App\Models\Combo::with('products')->find($comboId);
                foreach ($combo?->inventariableComponents ?? [] as $component) {
                    $qtyNeeded = $component->pivot->quantity * (int) $item['quantity'];
                    if ($component->stock_current < $qtyNeeded) {
                        throw new CheckoutException(
                            "Stock insuficiente para '{$component->name}' (componente de {$combo->name}). Disponible: {$component->stock_current}, Necesario: {$qtyNeeded}"
                        );
                    }
                }
            } else {
                $product = Product::find($itemId);
                if ($product && in_array($product->control_type, ['inventariable', 'produccion'])) {
                    if ($product->stock_current < (int) $item['quantity']) {
                        throw new CheckoutException(
                            "Stock insuficiente para '{$product->name}'. Disponible: {$product->stock_current}"
                        );
                    }
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
                $combo = \App\Models\Combo::with('products')->find($comboId);
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
        return (float) (ExchangeRate::latest()->first()?->rate ?? 1);
    }

    protected function nextSaleNumber(): string
    {
        $last = (int) Sale::max('id');
        return str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }
}
