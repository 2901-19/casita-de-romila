<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::when($request->filled('search'), fn ($q) => Like::apply($q, 'name', (string) $request->search))
            ->when($request->status === 'deuda', fn ($q) => $q->where('balance', '<', 0))
            ->when($request->status === 'favor', fn ($q) => $q->where('balance', '>', 0))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('credits.index', compact('customers', 'rate'));
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $data = $this->normalizeLimit($request->validated());
        Customer::create($data);

        return redirect()->route('credits.index')->with('success', 'Cliente creado exitosamente.');
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $this->normalizeLimit($request->validated());
        $customer->update($data);

        return redirect()->route('credits.show', $customer)->with('success', 'Cliente actualizado.');
    }

    public function show(Customer $customer): View
    {
        $customer->load(['movements.user', 'movements.sale']);

        $creditSales = Sale::where('customer_id', $customer->id)
            ->whereIn('status', ['pendiente', 'completada'])
            ->with(['items', 'creditMovements'])
            ->orderByRaw("CASE WHEN status = 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        $outstandingMap = [];
        $pendingUsd = 0;
        foreach ($creditSales as $cs) {
            $cargos = (float) $cs->creditMovements->where('type', 'cargo')->sum('amount');
            $pagos = (float) $cs->creditMovements->where('type', 'pago')->sum('amount');
            $outstanding = round($cargos - $pagos, 2);
            $outstandingMap[$cs->id] = $outstanding;
            if ($cs->status === 'pendiente' && $outstanding > 0) {
                $pendingUsd += $outstanding;
            }
        }
        $pendingUsd = round($pendingUsd, 2);

        $pending = $creditSales->where('status', 'pendiente');
        $pendingCount = $pending->count();

        return view('credits.show', compact('customer', 'creditSales', 'rate', 'pendingCount', 'pendingUsd', 'outstandingMap'));
    }

    public function payCredit(Request $request, Customer $customer, Sale $sale): RedirectResponse
    {
        if ($sale->customer_id !== $customer->id || $sale->status !== 'pendiente') {
            return redirect()->route('credits.show', $customer)
                ->with('error', 'Este crédito no puede cobrarse.');
        }

        try {
            DB::transaction(function () use ($sale, $customer, $request) {
                $locked = Sale::whereKey($sale->id)->lockForUpdate()->first();

                if (! $locked || $locked->customer_id !== $customer->id
                    || $locked->status !== 'pendiente' || $locked->paid_at !== null) {
                    throw new CheckoutException('Este crédito ya fue cobrado.');
                }

                $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);
                $outstanding = round((float) $locked->outstanding_usd, 2);

                CreditMovement::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $locked->id,
                    'user_id' => $request->user()->id,
                    'type' => 'pago',
                    'amount' => $outstanding,
                    'notes' => "Pago de venta #{$locked->id}",
                    'rate' => $rate,
                ]);

                SalePayment::create([
                    'sale_id' => $locked->id,
                    'method' => 'credito',
                    'amount' => round($outstanding * $rate, 2),
                ]);

                $customer->increment('balance', $outstanding);
                $locked->update(['status' => 'completada', 'paid_at' => now()]);
            });
        } catch (CheckoutException $e) {
            return redirect()->route('credits.show', $customer)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('credits.show', $customer)->with('error', 'Error al registrar el pago.');
        }

        return redirect()->route('credits.show', $customer)
            ->with('success', "Crédito #{$sale->id} cancelado.");
    }

    private function normalizeLimit(array $data): array
    {
        if (($data['credit_limit_type'] ?? 'libre') !== 'monto') {
            $data['credit_limit_type'] = 'libre';
            $data['credit_limit_amount'] = null;
        }

        return $data;
    }
}
