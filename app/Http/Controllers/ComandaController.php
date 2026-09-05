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
        $comandas = $this->buildListQuery($request, [Comanda::STATUS_MONTADA, Comanda::STATUS_ENTREGADA]);

        return view('comandas.index', ['comandas' => $comandas, 'scope' => 'active']);
    }

    public function history(Request $request): View
    {
        $comandas = $this->buildListQuery($request, [Comanda::STATUS_COBRADA]);

        return view('comandas.index', ['comandas' => $comandas, 'scope' => 'history']);
    }

    protected function buildListQuery(Request $request, array $statuses)
    {
        return Comanda::with(['user', 'items', 'payments'])
            ->whereIn('status', $statuses)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->order_type, fn ($q) => $q->whereHas('items', fn ($q) => $q->where('order_type', $request->order_type)))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function create(): View
    {
        $products = Product::query()->active()->orderBy('name')->get(['id', 'name', 'sale_price', 'round_bs', 'category_id', 'image', 'control_type']);
        $combos = Combo::active()->with('products')->orderBy('name')->get(['id', 'name', 'image', 'sale_price', 'round_bs']);
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
            'customer_name' => $validated['customer_name'] ?? null,
            'total' => $total,
            'sale_id' => null,
        ]);

        $this->saveItems($comanda, $items);

        return redirect()
            ->route('comandas.index')
            ->with('success', "Comanda #{$comanda->comanda_number} registrada.");
    }

    public function show(Comanda $comanda): View
    {
        $comanda->load(['user', 'items', 'payments']);
        $this->syncDeliveredStatus($comanda);

        $rate = $this->currentRate();
        $products = Product::query()->active()->orderBy('name')->get(['id', 'name', 'sale_price', 'round_bs', 'category_id', 'image', 'control_type']);
        $combos = Combo::active()->with('products')->orderBy('name')->get(['id', 'name', 'image', 'sale_price', 'round_bs']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('comandas.show', compact('comanda', 'products', 'combos', 'categories', 'customers', 'rate'));
    }

    public function update(Request $request, Comanda $comanda): RedirectResponse
    {
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', 'La comanda ya está cerrada y no se puede editar.');
        }

        $validated = $request->validate($this->baseRules());

        $rate = $this->currentRate();
        $items = $this->buildItems($validated['cart'], $rate);

        $this->saveItems($comanda, $items);

        $total = round((float) $comanda->items()->sum('subtotal'), 2);

        $comanda->update([
            'total' => $total,
            'customer_name' => $validated['customer_name'] ?? null,
        ]);

        return redirect()
            ->route('comandas.show', $comanda)
            ->with('success', 'Comanda actualizada.');
    }

    public function markDelivered(Comanda $comanda): RedirectResponse
    {
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda ya está cerrada.');
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
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda ya está cerrada.');
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
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda ya está cerrada.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:efectivo,biopago,pago_movil,pdv,credito'],
            'customer_id' => ['nullable', 'required_if:payment_method,credito', 'exists:customers,id'],
        ]);

        $comanda->load(['items', 'payments']);
        $pending = $comanda->items->where('collected', false);
        if ($pending->isEmpty()) {
            return redirect()->route('comandas.show', $comanda)->with('error', 'No hay items pendientes de cobro.');
        }

        // Crédito no se mezcla con otros métodos de pago.
        $hasCashPayments = $comanda->payments->where('method', '!=', 'credito')->isNotEmpty();
        $hasCreditPayments = $comanda->payments->where('method', 'credito')->isNotEmpty();
        if ($validated['payment_method'] === 'credito' && $hasCashPayments) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', 'Ya hay cobros en contado. No puede mezclar crédito con contado.');
        }
        if ($validated['payment_method'] !== 'credito' && $hasCreditPayments) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', 'Ya hay cobros a crédito. No puede mezclar contado con crédito.');
        }

        $amount = round($pending->sum(fn ($item) => (float) $item->subtotal), 2);

        ComandaItem::whereIn('id', $pending->pluck('id'))->update(['collected' => true]);
        $comanda->payments()->create([
            'amount' => $amount,
            'method' => $validated['payment_method'],
            'customer_id' => $validated['payment_method'] === 'credito' ? $validated['customer_id'] : null,
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('comandas.show', $comanda)
            ->with('success', 'Cobro registrado (Bs ' . number_format($amount, 2, ',', '.') . ').');
    }

    public function close(Request $request, Comanda $comanda): RedirectResponse
    {
        if ($comanda->status === Comanda::STATUS_COBRADA) {
            return redirect()->route('comandas.show', $comanda)->with('error', 'La comanda ya está cerrada.');
        }

        $comanda->load(['items', 'payments']);
        if (! $comanda->isFullyCollected()) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', 'La comanda no está cobrada en su totalidad. Registra primero los cobros pendientes.');
        }

        try {
            $this->checkout->closeComanda($comanda, $request->user()->id);
        } catch (CheckoutException $e) {
            return redirect()
                ->route('comandas.show', $comanda)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('comandas.index')
            ->with('success', 'Comanda cerrada. Venta registrada.');
    }

    protected function baseRules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:150'],
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.product_id' => ['required'],
            'cart.*.quantity' => ['required', 'integer', 'min:1'],
            'cart.*.order_type' => ['required', 'in:' . implode(',', [
                Comanda::ORDER_DELIVERY,
                Comanda::ORDER_LOCAL,
                Comanda::ORDER_PARA_LLEVAR,
            ])],
            'cart.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function buildItems(array $cart, float $rate): array
    {
        $items = [];
        foreach ($cart as $row) {
            $itemId = $row['product_id'];
            $qty = (int) $row['quantity'];
            $orderType = $row['order_type'] ?? ComandaItem::ORDER_LOCAL;
            $note = $row['note'] ?? null;

            if (str_starts_with((string) $itemId, 'combo_')) {
                $combo = Combo::find((int) substr((string) $itemId, 6));
                if (! $combo) {
                    continue;
                }
                $unitPrice = \App\Support\Pricing::bs((float) $combo->sale_price, $rate, $combo->round_bs);
                $items[] = [
                    'product_id' => null,
                    'combo_id' => $combo->id,
                    'name' => $combo->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'order_type' => $orderType,
                    'note' => $note,
                ];
            } else {
                $product = Product::find((int) $itemId);
                if (! $product) {
                    continue;
                }
                $unitPrice = \App\Support\Pricing::bs((float) $product->sale_price, $rate, $product->round_bs);
                $items[] = [
                    'product_id' => $product->id,
                    'combo_id' => null,
                    'name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'order_type' => $orderType,
                    'note' => $note,
                ];
            }
        }
        return $items;
    }

    /**
     * Crea los items no cobrados y conserva (trabados) los ya cobrados.
     * Los items cobrados no pueden editarse ni eliminarse; solo nuevos items
     * (sin item_id) y los pendientes (collected=false) se reemplazan.
     */
    protected function saveItems(Comanda $comanda, array $items): void
    {
        $pendingIds = $comanda->items()
            ->where('collected', false)
            ->pluck('id');

        if ($pendingIds->isNotEmpty()) {
            ComandaItem::whereIn('id', $pendingIds)->delete();
        }

        foreach ($items as $item) {
            $comanda->items()->create([
                'product_id' => $item['product_id'],
                'combo_id' => $item['combo_id'],
                'product_name' => $item['name'],
                'order_type' => $item['order_type'],
                'note' => $item['note'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => round($item['unit_price'] * $item['quantity'], 2),
                'delivered_quantity' => 0,
                'delivered_at' => null,
                'collected' => false,
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
