<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $adjustments = InventoryAdjustment::with(['product', 'user'])
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->reason, fn($q) => $q->where('reason', $request->reason))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $products = Product::orderBy('name')->get();
        $adjustableProducts = Product::where('control_type', 'inventariable')
            ->orderBy('name')
            ->get();
        $stats = [
            'entradas' => InventoryAdjustment::where('type', 'entrada')->sum('quantity'),
            'salidas' => InventoryAdjustment::where('type', 'salida')->sum('quantity'),
        ];

        return view('inventory.index', compact('adjustments', 'products', 'adjustableProducts', 'stats'));
    }

    public function store(StoreInventoryAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::find($data['product_id']);

        if (! $product) {
            return back()->withErrors(['product_id' => 'Producto no encontrado.'])->withInput();
        }

        if ($data['type'] === 'salida' && $product->stock_current < $data['quantity']) {
            return back()->withErrors([
                'quantity' => "Stock insuficiente. Disponible: {$product->stock_current}",
            ])->withInput();
        }

        \DB::transaction(function () use ($data, $request, $product) {
            $adjustment = InventoryAdjustment::create([
                'product_id' => $data['product_id'],
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);

            if ($data['type'] === 'entrada') {
                $product->increment('stock_current', $data['quantity']);
            } else {
                $product->decrement('stock_current', $data['quantity']);
            }
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Ajuste registrado exitosamente.');
    }
}
