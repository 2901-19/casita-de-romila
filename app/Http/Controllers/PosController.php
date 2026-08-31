<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Http\Requests\CheckoutRequest;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
    ) {
    }

    public function index(): View
    {
        $products = Product::query()->active()->orderBy('name')->get();
        $combos = Combo::active()->with('products')->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('pos.index', compact('products', 'combos', 'categories', 'customers', 'rate'));
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $cart = $validated['cart'];

        if (empty($cart)) {
            return response()->json(['error' => 'El carrito está vacío.'], 422);
        }

        try {
            $sale = $this->checkout->execute(
                cart: $cart,
                paymentMethod: $validated['payment_method'],
                customerId: $validated['customer_id'] ?? null,
                userId: $request->user()->id,
            );
        } catch (CheckoutException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => $validated['payment_method'] === 'credito'
                ? "Venta a crédito registrada a nombre de {$sale->customer_name}."
                : 'Venta procesada exitosamente.',
            'sale_id' => $sale->id,
        ]);
    }
}
