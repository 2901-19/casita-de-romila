<?php
namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\ExchangeRate;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::with(['user', 'payments', 'items'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('sales.index', compact('sales'));
    }

    public function show(Sale $sale): View
    {
        $sale->load(['user', 'payments', 'items.product']);
        return view('sales.show', compact('sale'));
    }

    public function destroy(Request $request, Sale $sale): RedirectResponse
    {
        if ($sale->status === 'anulada') {
            return redirect()->route('sales.index')->with('error', 'Esta venta ya está anulada.');
        }

        $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ]);

        \DB::transaction(function () use ($sale, $request) {
            foreach ($sale->items as $item) {
                $product = $item->product;
                if ($product && in_array($product->control_type, ['inventariable', 'produccion'])) {
                    $product->increment('stock_current', $item->quantity);
                }

                if ($item->combo_id) {
                    $combo = Combo::with('inventariableComponents')->find($item->combo_id);
                    if ($combo) {
                        foreach ($combo->inventariableComponents as $component) {
                            $qtyToRestore = $component->pivot->quantity * $item->quantity;
                            $component->increment('stock_current', $qtyToRestore);
                        }
                    }
                }
            }

            if ($sale->customer_id && in_array($sale->status, ['pendiente', 'completada'])) {
                $outstanding = $sale->outstanding_usd;

                if ($outstanding > 0) {
                    $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

                    \App\Models\CreditMovement::create([
                        'customer_id' => $sale->customer_id,
                        'sale_id' => $sale->id,
                        'user_id' => $request->user()->id,
                        'type' => 'abono',
                        'amount' => $outstanding,
                        'rate' => $rate,
                        'notes' => "Reversa por anulación de venta #{$sale->id}",
                    ]);

                    \App\Models\Customer::find($sale->customer_id)?->increment('balance', $outstanding);
                }
            }

            $sale->update([
                'status' => 'anulada',
                'cancel_reason' => $request->cancel_reason,
                'canceled_by' => $request->user()->id,
                'canceled_at' => now(),
            ]);
        });

        return redirect()
            ->route('sales.index')
            ->with('success', 'Venta anulada. Stock restaurado.');
    }
}
