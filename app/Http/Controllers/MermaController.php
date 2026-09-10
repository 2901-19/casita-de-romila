<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreMermaRequest;
use App\Models\Merma;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class MermaController extends Controller
{
    public function index(Request $request): View
    {
        $mermas = Merma::with(['product', 'user'])
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->type === 'merma', fn($q) => $q->where(fn($q2) => $q2->whereNull('type')->orWhere('type', 'merma')))
            ->when($request->type === 'consumo', fn($q) => $q->where('type', 'consumo'))
            ->when($request->reason, fn($q) => $q->where('reason', $request->reason))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $products = Product::where('is_active', true)->orderBy('name')->get();
        $totalToday = Merma::whereDate('created_at', now()->toDateString())
            ->where(fn($q) => $q->whereNull('type')->orWhere('type', 'merma'))
            ->sum('quantity');
        $totalConsumption = Merma::whereDate('created_at', now()->toDateString())
            ->where('type', 'consumo')
            ->sum('quantity');
        $selectedType = $request->type === 'consumo' ? 'consumo' : ($request->type === 'merma' ? 'merma' : '');

        return view('mermas.index', compact('mermas', 'products', 'totalToday', 'totalConsumption', 'selectedType'));
    }

    public function store(StoreMermaRequest $request)
    {
        $data = $request->validated();

        $product = Product::find($data['product_id']);
        if (! $product) {
            return back()->withErrors(['product_id' => 'Producto no encontrado.'])->withInput();
        }
        if ($product->stock_current < $data['quantity']) {
            return back()->withErrors([
                'quantity' => "Stock insuficiente. Disponible: {$product->stock_current}",
            ])->withInput();
        }

        DB::transaction(function () use ($data, $request, $product) {
            $merma = Merma::create([
                'product_id' => $data['product_id'],
                'user_id' => $request->user()->id,
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
            ]);

            $product->decrement('stock_current', $data['quantity']);

            $isConsumption = $data['type'] === 'consumo';

            \App\Models\InventoryAdjustment::create([
                'product_id' => $data['product_id'],
                'user_id' => $request->user()->id,
                'type' => 'salida',
                'quantity' => $data['quantity'],
                'reason' => 'merma',
                'notes' => ($isConsumption ? 'Consumo interno: ' : 'Merma: ') . ($data['reason'] ?? ''),
            ]);
        });

        return redirect()
            ->route('mermas.index')
            ->with('success', $data['type'] === 'consumo'
                ? 'Consumo interno registrado. Stock actualizado.'
                : 'Merma registrada. Stock actualizado.');
    }
}
