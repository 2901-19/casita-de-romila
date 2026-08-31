<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Models\Category;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ComandaController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
    ) {
    }

    public function index(Request $request): View
    {
        $comandas = Comanda::with(['user', 'items'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->order_type, fn ($q) => $q->where('order_type', $request->order_type))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('comandas.index', compact('comandas'));
    }

    public function create(): View
    {
        $products = Product::query()->active()->orderBy('name')->get(['id', 'name', 'sale_price', 'category_id', 'image']);
        $combos = Combo::active()->with('products')->orderBy('name')->get(['id', 'name', 'sale_price']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('comandas.create', compact('products', 'combos', 'categories', 'rate'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->baseRules());

        $rate = $this->currentRate();
        $items = $this->buildItems($validated['cart'], $rate);
        $total = $this->sumItems($items);

        $comanda = Comanda::create([
            'comanda_number' => $this->nextComandaNumber(),
            'user_id' => $request->user()->id,
            'status' => Comanda::STATUS_MONTADA,
            'order_type' => $validated['order_type'],
            'customer_name' => $validated['order_type'] === Comanda::ORDER_DELIVERY ? ($validated['customer_name'] ?? null) : null,
            'notes' => $validated['notes'] ?? null,
            'total' => $total,
            'sale_id' => null,
        ]);

        $this->saveItems($comanda, $items);

        return redirect()
            ->route('comandas.show', $comanda)
            ->with('success', "Comanda #{$comanda->comanda_number} registrada.");
    }

    public function show(Comanda $comanda): View
    {
        $comanda->load(['user', 'items']);
        $this->syncDeliveredStatus($comanda);

        $rate = $this->currentRate();
        $products = Product::query()->active()->orderBy('name')->get(['id', 'name', 'sale_price', 'category_id', 'image']);
        $combos = Combo::active()->with('products')->orderBy('name')->get(['id', 'name', 'sale_price']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('comandas.show', compact('comanda', 'products', 'combos', 'categories', 'customers', 'rate'));
    }

    public function update(Request $request, Comanda $comanda): RedirectResponse
    {
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', 'La comanda ya está cobrada y no se puede editar.');
        }

        $validated = $request->validate($this->baseRules());

        $rate = $this->currentRate();
        $items = $this->buildItems($validated['cart'], $rate);
        $total = $this->sumItems($items);

        $comanda->items()->delete();
        $this->saveItems($comanda, $items);

        $comanda->update([
            'total' => $total,
            'order_type' => $validated['order_type'],
            'customer_name' => $validated['order_type'] === Comanda::ORDER_DELIVERY ? ($validated['customer_name'] ?? null) : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('comandas.show', $comanda)
            ->with('success', 'Comanda actualizada.');
    }

    public function markDelivered(Comanda $comanda): RedirectResponse
    {
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda ya está cobrada.');
        }

        $comanda->items()->update(['delivered_quantity' => DB::raw('quantity'), 'delivered_at' => now()]);
        $comanda->update(['status' => Comanda::STATUS_ENTREGADA]);

        return redirect()->route('comandas.show', $comanda)->with('success', 'Comanda marcada como entregada.');
    }

    public function deliverItem(Comanda $comanda, ComandaItem $item): RedirectResponse
    {
        if ($item->comanda_id !== $comanda->id) {
            abort(404);
        }
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda ya está cobrada.');
        }

        if ($item->delivered_quantity < $item->quantity) {
            $item->increment('delivered_quantity');
            if ($item->delivered_quantity >= $item->quantity) {
                $item->update(['delivered_at' => now()]);
            }
        }
        $this->syncDeliveredStatus($comanda);

        return redirect()->route('comandas.show', $comanda);
    }

    public function collect(Request $request, Comanda $comanda): RedirectResponse
    {
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda ya está cobrada.');
        }

        $isDelivery = $comanda->is_delivery;
        if (! $isDelivery && $comanda->status !== Comanda::STATUS_ENTREGADA) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', 'Debe marcar la comanda como entregada antes de cobrar.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:efectivo,biopago,pago_movil,pdv,credito'],
            'customer_id' => ['nullable', 'required_if:payment_method,credito', 'exists:customers,id'],
        ]);

        $comanda->load('items');
        if ($comanda->items->isEmpty()) {
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda no tiene productos.');
        }

        $cart = $comanda->items->map(fn ($item) => [
            'product_id' => $item->combo_id ? "combo_{$item->combo_id}" : $item->product_id,
            'name' => $item->product_name,
            'price' => (float) $item->unit_price,
            'quantity' => (int) $item->quantity,
        ])->values()->all();

        try {
            $this->checkout->execute(
                cart: $cart,
                paymentMethod: $validated['payment_method'],
                customerId: $validated['customer_id'] ?? null,
                userId: $request->user()->id,
                comandaId: $comanda->id,
            );
        } catch (CheckoutException $e) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('comandas.show', $comanda)
            ->with('success', 'Comanda cobrada. Venta registrada.');
    }

    protected function baseRules(): array
    {
        return [
            'order_type' => ['required', 'in:' . implode(',', [
                Comanda::ORDER_DELIVERY,
                Comanda::ORDER_LOCAL,
                Comanda::ORDER_PARA_LLEVAR,
            ])],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:255'],
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.product_id' => ['required'],
            'cart.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function buildItems(array $cart, float $rate): array
    {
        $items = [];
        foreach ($cart as $row) {
            $itemId = $row['product_id'];
            $qty = (int) $row['quantity'];

            if (str_starts_with((string) $itemId, 'combo_')) {
                $combo = Combo::find((int) substr((string) $itemId, 6));
                if (! $combo) {
                    continue;
                }
                $unitPrice = round((float) $combo->sale_price * $rate, 2);
                $items[] = [
                    'product_id' => null,
                    'combo_id' => $combo->id,
                    'name' => $combo->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ];
            } else {
                $product = Product::find((int) $itemId);
                if (! $product) {
                    continue;
                }
                $unitPrice = round((float) $product->sale_price * $rate, 2);
                $items[] = [
                    'product_id' => $product->id,
                    'combo_id' => null,
                    'name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ];
            }
        }
        return $items;
    }

    protected function saveItems(Comanda $comanda, array $items): void
    {
        foreach ($items as $item) {
            $comanda->items()->create([
                'product_id' => $item['product_id'],
                'combo_id' => $item['combo_id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => round($item['unit_price'] * $item['quantity'], 2),
                'delivered_quantity' => 0,
                'delivered_at' => null,
            ]);
        }
    }

    protected function sumItems(array $items): float
    {
        return round(array_reduce(
            $items,
            fn ($sum, $it) => $sum + ($it['unit_price'] * $it['quantity']),
            0
        ), 2);
    }

    protected function syncDeliveredStatus(Comanda $comanda): void
    {
        $comanda->loadMissing('items');
        if ($comanda->status === Comanda::STATUS_MONTADA && $comanda->allItemsDelivered()) {
            $comanda->update(['status' => Comanda::STATUS_ENTREGADA]);
        }
    }

    protected function nextComandaNumber(): string
    {
        $count = Comanda::whereDate('created_at', now()->toDateString())->count();
        return str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function currentRate(): float
    {
        return (float) (ExchangeRate::latest()->first()?->rate ?? 1);
    }
}
