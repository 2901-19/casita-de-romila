<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/products');

        $response->assertStatus(200);
        $response->assertViewIs('products.index');
    }

    public function test_create_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/products/create');

        $response->assertStatus(200);
        $response->assertViewIs('products.create');
    }

    public function test_stores_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $category = Category::factory()->create();

        $response = $this->post('/products', [
            'name' => 'Torta de Chocolate',
            'category_id' => $category->id,
            'control_type' => 'produccion',
            'cost_price' => 1.20,
            'margin_percent' => 67,
            'sale_price' => 4.00,
            'stock_min' => 5,
            'stock_current' => 10,
            'schedule' => 'ambos',
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['name' => 'Torta de Chocolate', 'control_type' => 'produccion']);
    }

    public function test_validates_name_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/products', [
            'name' => '',
            'control_type' => 'inventariable',
            'cost_price' => 1.00,
            'margin_percent' => 50,
            'sale_price' => 2.00,
            'stock_min' => 5,
            'stock_current' => 10,
            'schedule' => 'ambos',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_validates_cost_price_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/products', [
            'name' => 'Producto Test',
            'control_type' => 'inventariable',
            'cost_price' => '',
            'margin_percent' => 50,
            'sale_price' => 2.00,
            'stock_min' => 5,
            'stock_current' => 10,
            'schedule' => 'ambos',
        ]);

        $response->assertSessionHasErrors('cost_price');
    }

    public function test_updates_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['name' => 'Antiguo']);

        $response = $this->put("/products/{$product->id}", [
            'name' => 'Nuevo Nombre',
            'control_type' => $product->control_type,
            'cost_price' => $product->cost_price,
            'margin_percent' => $product->margin_percent,
            'sale_price' => $product->sale_price,
            'stock_min' => $product->stock_min,
            'stock_current' => $product->stock_current,
            'schedule' => $product->schedule,
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['name' => 'Nuevo Nombre']);
    }

    public function test_toggles_active_status(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->patch("/products/{$product->id}/toggle-active");

        $response->assertStatus(302);
        $product->refresh();
        $this->assertFalse($product->is_active);
    }

    public function test_deletes_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create();

        $response = $this->delete("/products/{$product->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_stores_product_with_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $image = UploadedFile::fake()->image('torta.jpg');

        $response = $this->post('/products', [
            'name' => 'Torta con Foto',
            'control_type' => 'inventariable',
            'cost_price' => 1,
            'sale_price' => 2,
            'schedule' => 'ambos',
            'is_active' => true,
            'image' => $image,
        ]);

        $response->assertSessionHas('success');
        $product = Product::where('name', 'Torta con Foto')->first();

        $this->assertNotNull($product->image);
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_update_replaces_product_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $oldPath = UploadedFile::fake()->image('vieja.png')->store('products', 'public');
        $product = Product::factory()->create(['image' => $oldPath]);

        $response = $this->put("/products/{$product->id}", [
            'name' => 'Torta Actualizada',
            'control_type' => 'inventariable',
            'cost_price' => 1,
            'sale_price' => 2,
            'schedule' => 'ambos',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('nueva.jpg'),
        ]);

        $response->assertSessionHas('success');
        $product->refresh();

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertNotEquals($oldPath, $product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_update_can_remove_product_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $path = UploadedFile::fake()->image('foto.jpg')->store('products', 'public');
        $product = Product::factory()->create(['image' => $path]);

        $response = $this->put("/products/{$product->id}", [
            'name' => 'Sin Imagen',
            'control_type' => 'inventariable',
            'cost_price' => 1,
            'sale_price' => 2,
            'schedule' => 'ambos',
            'is_active' => true,
            'remove_image' => '1',
        ]);

        $response->assertSessionHas('success');
        $product->refresh();

        $this->assertNull($product->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_rejects_invalid_image_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->post('/products', [
            'name' => 'Producto Invalido',
            'control_type' => 'inventariable',
            'cost_price' => 1,
            'sale_price' => 2,
            'schedule' => 'ambos',
            'image' => $file,
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertDatabaseMissing('products', ['name' => 'Producto Invalido']);
    }

    public function test_deletes_product_with_image_removes_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $path = UploadedFile::fake()->image('foto.jpg')->store('products', 'public');
        $product = Product::factory()->create(['image' => $path]);

        $response = $this->delete("/products/{$product->id}");

        $response->assertSessionHas('success');
        Storage::disk('public')->assertMissing($path);
    }

    public function test_bulk_toggle_rejects_invalid_ids(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->postJson('/products/bulk-toggle', [
            'ids' => 'not-an-array',
            'action' => 'activate',
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_delete_rejects_nonexistent_ids(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->deleteJson('/products/bulk-delete', [
            'ids' => [99999],
        ]);

        $response->assertStatus(422);
    }
}
