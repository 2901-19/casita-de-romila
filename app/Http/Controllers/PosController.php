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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
    ) {
    }

    public function index(): View
    {
        $categories = Category::orderBy('name')->get();
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('pos.index', compact('categories', 'customers', 'rate'));
    }

    public function products(Request $request): JsonResponse
    {
        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);
        $search = strtolower(trim((string) $request->query('search', '')));
        $categoryId = $request->query('category_id');

        $products = Product::active()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sale_price' => round((float) $p->sale_price * $rate, 2),
                'category_id' => $p->category_id,
                'image' => $p->image ? asset('storage/'.$p->image) : '',
                'is_combo' => false,
            ]);

        $combos = Combo::active()
            ->with(['products' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Combo $combo) => [
                'id' => 'combo_'.$combo->id,
                'name' => $combo->name,
                'sale_price' => round((float) $combo->sale_price * $rate, 2),
                'category_id' => null,
                'image' => '',
                'is_combo' => true,
                'components' => $combo->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'quantity' => $p->pivot->quantity,
                ])->values()->toArray(),
            ]);

        $catalog = (new Collection([...$products->all(), ...$combos->all()]))
            ->sortBy('name')
            ->values()
            ->filter(fn ($item) => $categoryId === null || $categoryId === '' || $item['category_id'] == $categoryId)
            ->filter(fn ($item) => $search === '' || str_contains(strtolower($item['name']), $search))
            ->values();

        $perPage = 9;
        $page = max(1, (int) $request->query('page', 1));
        $total = $catalog->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $items = $catalog->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'items' => $items,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'rate' => $rate,
        ]);
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
