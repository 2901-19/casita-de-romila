<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComboRequest;
use App\Http\Requests\UpdateComboRequest;
use App\Models\Combo;
use App\Models\ExchangeRate;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComboController extends Controller
{
    public function index(Request $request): View
    {
        $query = Combo::with('products');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $isActive = $request->input('status') === 'activo';
            $query->where('is_active', $isActive);
        }

        $combos = $query->orderBy('name')->paginate(10)->withQueryString();

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('combos.index', compact('combos', 'rate'));
    }

    public function create(): View
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->get();

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('combos.create', compact('products', 'rate'));
    }

    public function store(StoreComboRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $products = $data['products'];
        unset($data['products']);

        $combo = Combo::create($data);

        foreach ($products as $product) {
            $combo->products()->attach($product['id'], ['quantity' => $product['quantity']]);
        }

        return redirect()
            ->route('combos.index')
            ->with('success', 'Combo creado exitosamente.');
    }

    public function edit(Combo $combo): View
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->get();

        $combo->load('products');

        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('combos.edit', compact('combo', 'products', 'rate'));
    }

    public function update(UpdateComboRequest $request, Combo $combo): RedirectResponse
    {
        $data = $request->validated();
        $products = $data['products'];
        unset($data['products']);

        $combo->update($data);

        $combo->products()->detach();

        foreach ($products as $product) {
            $combo->products()->attach($product['id'], ['quantity' => $product['quantity']]);
        }

        return redirect()
            ->route('combos.index')
            ->with('success', 'Combo actualizado.');
    }

    public function toggleActive(Combo $combo): RedirectResponse
    {
        $combo->update(['is_active' => ! $combo->is_active]);

        $status = $combo->is_active ? 'activado' : 'desactivado';

        return redirect()
            ->route('combos.index')
            ->with('success', "Combo {$status}.");
    }

    public function destroy(Combo $combo): RedirectResponse
    {
        if ($combo->saleItems()->exists()) {
            return redirect()
                ->route('combos.index')
                ->with('error', 'No se puede eliminar el combo "' . $combo->name . '" porque tiene ventas registradas.');
        }

        $combo->products()->detach();
        $combo->delete();

        return redirect()
            ->route('combos.index')
            ->with('success', 'Combo eliminado.');
    }
}
