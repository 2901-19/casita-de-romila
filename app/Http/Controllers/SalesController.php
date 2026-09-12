<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Sale;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::with(['user', 'payments', 'items'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when(Dates::valid($request->input('from')), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when(Dates::valid($request->input('to')), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
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

        $revertedToPending = false;

        \DB::transaction(function () use ($sale, $request, &$revertedToPending) {
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

            $isPaidCredit = $sale->payment_method === 'credito' && $sale->status === 'completada';

            if ($isPaidCredit) {
                // Crédito que ya fue cobrado: se revierte el pago y la venta
                // vuelve a 'pendiente' (el cliente recupera su deuda). El cargo
                // original se conserva; solo se elimina el movimiento 'pago'.
                $paidUsd = (float) $sale->creditMovements()->where('type', 'pago')->sum('amount');

                if ($paidUsd > 0) {
                    $sale->creditMovements()->where('type', 'pago')->delete();
                    Customer::find($sale->customer_id)?->decrement('balance', $paidUsd);
                }

                $sale->update([
                    'status' => 'pendiente',
                    'paid_at' => null,
                    'cancel_reason' => null,
                    'canceled_by' => null,
                    'canceled_at' => null,
                ]);

                $revertedToPending = true;
            } elseif ($sale->customer_id && $sale->status === 'pendiente') {
                $outstanding = $sale->outstanding_usd;

                if ($outstanding > 0) {
                    $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

                    CreditMovement::create([
                        'customer_id' => $sale->customer_id,
                        'sale_id' => $sale->id,
                        'user_id' => $request->user()->id,
                        'type' => 'abono',
                        'amount' => $outstanding,
                        'rate' => $rate,
                        'notes' => "Reversa por anulación de venta #{$sale->id}",
                    ]);

                    Customer::find($sale->customer_id)?->increment('balance', $outstanding);
                }
            }

            // Los pagos de una venta anulada dejan de contar como ingresos.
            $sale->payments()->delete();

            if (! $revertedToPending) {
                $sale->update([
                    'status' => 'anulada',
                    'cancel_reason' => $request->cancel_reason,
                    'canceled_by' => $request->user()->id,
                    'canceled_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('sales.index')
            ->with('success', $revertedToPending
                ? 'Pago del crédito revertido; el crédito vuelve a pendiente. Stock restaurado.'
                : 'Venta anulada. Stock restaurado.');
    }
}
