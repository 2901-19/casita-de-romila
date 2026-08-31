<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category_id', $request->input('category'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $isActive = $request->input('status') === 'activo';
            $query->where('is_active', $isActive);
        }

        $products = $query->orderBy('name')->paginate(10)->withQueryString();
        $categories = Category::orderBy('name')->get();
        $types = ProductType::cases();
        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);

        return view('products.index', compact('products', 'categories', 'types', 'rate'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto creado exitosamente.');
    }

    public function create(): View
    {
        $categories = Category::orderBy('name')->get();
        $types = ProductType::cases();
        $activeRate = ExchangeRate::latest()->first();

        return view('products.create', compact('categories', 'types', 'activeRate'));
    }

    public function edit(Product $product): View
    {
        $categories = Category::orderBy('name')->get();
        $types = ProductType::cases();
        $activeRate = ExchangeRate::latest()->first();

        return view('products.edit', compact('product', 'categories', 'types', 'activeRate'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        unset($data['remove_image']);

        if ($request->boolean('remove_image')) {
            $this->deleteImage($product);
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteImage($product);
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto actualizado.');
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        $status = $product->is_active ? 'activado' : 'desactivado';

        return redirect()
            ->route('products.index')
            ->with('success', "Producto {$status}.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteImage($product);
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto eliminado.');
    }

    private function deleteImage(Product $product): void
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
    }

    public function bulkToggle(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
            'action' => ['required', \Illuminate\Validation\Rule::in(['activate', 'deactivate'])],
        ]);

        Product::whereIn('id', $validated['ids'])
            ->update(['is_active' => $validated['action'] === 'activate']);

        return response()->json(['ok' => true]);
    }

    public function bulkDelete(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        Product::whereIn('id', $validated['ids'])->delete();

        return response()->json(['ok' => true]);
    }
}
