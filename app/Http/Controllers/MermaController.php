<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreMermaRequest;
use App\Models\Merma;
use App\Models\Product;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class MermaController extends Controller
{
    public function index(Request $request): View
    {
        $mermas = Merma::with(['product', 'user'])
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->type === 'merma', fn($q) => $q->mermaType())
            ->when($request->type === 'consumo', fn($q) => $q->consumption())
            ->when($request->reason, fn($q) => $q->where('reason', $request->reason))
            ->when(Dates::valid($request->input('from')), fn($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when(Dates::valid($request->input('to')), fn($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $products = Product::where('is_active', true)->orderBy('name')->get();
        $totalToday = Merma::mermaType()
            ->whereDate('created_at', now()->toDateString())
            ->sum('quantity');
        $totalConsumption = Merma::consumption()
            ->whereDate('created_at', now()->toDateString())
            ->sum('quantity');
        $totalConsumptionCost = Merma::consumption()
            ->whereDate('created_at', now()->toDateString())
            ->get()
            ->sum(fn($m) => $m->subtotal() ?? 0);
        $selectedType = $request->type === 'consumo' ? 'consumo' : ($request->type === 'merma' ? 'merma' : '');

        return view('mermas.index', compact('mermas', 'products', 'totalToday', 'totalConsumption', 'totalConsumptionCost', 'selectedType'));
    }

    public function store(StoreMermaRequest $request)
    {
        $data = $request->validated();
        $isConsumption = $data['type'] === 'consumo';

        $products = Product::whereIn('id', collect($data['lines'])->pluck('product_id'))->get()->keyBy('id');

        foreach ($data['lines'] as $i => $line) {
            $product = $products->get($line['product_id']);
            if (! $product) {
                return back()->withErrors(["lines.{$i}.product_id" => 'Producto no encontrado.'])->withInput();
            }
            if ($product->stock_current < $line['quantity']) {
                return back()->withErrors([
                    "lines.{$i}.quantity" => "Producto \"{$product->name}\": stock insuficiente. Disponible: {$product->stock_current}",
                ])->withInput();
            }
        }

        DB::transaction(function () use ($data, $request, $products, $isConsumption) {
            foreach ($data['lines'] as $line) {
                $product = $products->get($line['product_id']);
                $cost = $isConsumption ? (float) ($line['cost'] ?? $product->cost_price) : null;

                Merma::create([
                    'product_id' => $line['product_id'],
                    'user_id' => $request->user()->id,
                    'quantity' => $line['quantity'],
                    'cost' => $cost,
                    'reason' => $data['reason'],
                    'type' => $data['type'],
                    'notes' => $data['notes'] ?? null,
                ]);

                $product->decrement('stock_current', $line['quantity']);

                \App\Models\InventoryAdjustment::create([
                    'product_id' => $line['product_id'],
                    'user_id' => $request->user()->id,
                    'type' => 'salida',
                    'quantity' => $line['quantity'],
                    'reason' => 'merma',
                    'notes' => ($isConsumption ? 'Consumo interno: ' : 'Merma: ') . ($data['reason'] ?? ''),
                ]);
            }
        });

        return redirect()
            ->route('mermas.index')
            ->with('success', $isConsumption
                ? 'Consumo interno registrado. Stock actualizado.'
                : 'Merma registrada. Stock actualizado.');
    }
}
