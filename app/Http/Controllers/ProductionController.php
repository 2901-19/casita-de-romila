<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionRequest;
use App\Models\Product;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(Request $request): View
    {
        $productions = Production::with(['product', 'user'])
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $products = Product::where('control_type', 'produccion')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $totalToday = Production::whereDate('created_at', now()->toDateString())->sum('quantity');

        return view('productions.index', compact('productions', 'products', 'totalToday'));
    }

    public function store(StoreProductionRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $production = Production::create([
                'product_id' => $data['product_id'],
                'user_id' => $request->user()->id,
                'quantity' => $data['quantity'],
                'notes' => $data['notes'] ?? null,
            ]);

            Product::whereKey($data['product_id'])->increment('stock_current', $data['quantity']);

            \App\Models\InventoryAdjustment::create([
                'product_id' => $data['product_id'],
                'production_id' => $production->id,
                'user_id' => $request->user()->id,
                'type' => 'entrada',
                'quantity' => $data['quantity'],
                'reason' => 'produccion',
                'notes' => 'Producción registrada',
            ]);
        });

        return redirect()
            ->route('productions.index')
            ->with('success', 'Producción registrada. Stock actualizado.');
    }

    public function destroy(Production $production)
    {
        if (! $production->isUndoable()) {
            return redirect()
                ->route('productions.index')
                ->with('error', 'Solo puedes eliminar registros de producción dentro de los primeros 20 minutos.');
        }

        $product = $production->product;

        if ($product->stock_current < $production->quantity) {
            return redirect()
                ->route('productions.index')
                ->with('error', "No se puede eliminar: el stock actual de {$product->name} es menor a las {$production->quantity} unidades producidas.");
        }

        DB::transaction(function () use ($production) {
            $production->product->decrement('stock_current', $production->quantity);
            $production->adjustment()->delete();
            $production->delete();
        });

        return redirect()
            ->route('productions.index')
            ->with('success', 'Registro de producción eliminado. Stock actualizado.');
    }
}
