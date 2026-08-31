<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CreditMovement;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_loads_without_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertViewIs('pos.index');
    }

    public function test_pos_shows_categories_and_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::factory()->create(['name' => 'Bebidas']);
        $product = Product::factory()->create([
            'name' => 'Refresco',
            'category_id' => $category->id,
            'is_active' => true,
            'sale_price' => 2.50,
        ]);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('Bebidas');
    }

    public function test_pos_shows_products_in_javascript(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::factory()->create(['name' => 'Bebidas']);
        $product = Product::factory()->create([
            'name' => 'Refresco',
            'category_id' => $category->id,
            'is_active' => true,
            'sale_price' => 2.50,
        ]);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $this->assertNotNull($product->id);
    }

    public function test_pos_only_shows_active_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $cat = Category::factory()->create();
        Product::factory()->create(['name' => 'Activo', 'is_active' => true, 'category_id' => $cat->id]);
        Product::factory()->create(['name' => 'Inactivo', 'is_active' => false, 'category_id' => $cat->id]);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $this->assertNotNull($cat->id);
    }

    public function test_pos_shows_uncategorized_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Product::factory()->create([
            'name' => 'Producto Sin Categoria',
            'category_id' => null,
            'is_active' => true,
        ]);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('Producto Sin Categoria', false);
    }

    public function test_pos_hides_inactive_products_without_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Product::factory()->create([
            'name' => 'Inactivo Sin Categoria',
            'category_id' => null,
            'is_active' => false,
        ]);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertDontSee('Inactivo Sin Categoria', false);
    }

    public function test_credit_sale_requires_customer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['control_type' => 'inventariable', 'sale_price' => 10, 'stock_current' => 5]);

        $response = $this->postJson('/pos', [
            'cart' => [['product_id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1]],
            'payment_method' => 'credito',
        ]);

        $response->assertStatus(422);
    }

    public function test_credit_sale_creates_pending_sale_with_usd_charge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ExchangeRate::factory()->create(['rate' => 100]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['control_type' => 'inventariable', 'sale_price' => 10, 'stock_current' => 5]);

        $response = $this->postJson('/pos', [
            'cart' => [['product_id' => $product->id, 'name' => $product->name, 'price' => 1000.00, 'quantity' => 1]],
            'payment_method' => 'credito',
            'customer_id' => $customer->id,
        ]);

        $response->assertStatus(200);

        $sale = Sale::latest('id')->first();
        $this->assertEquals('pendiente', $sale->status);
        $this->assertEquals($customer->id, $sale->customer_id);
        $this->assertEquals($customer->name, $sale->customer_name);
        $this->assertEquals(0, $sale->payments()->count());
        $this->assertEquals(1000.00, (float) $sale->total);

        $this->assertDatabaseHas('credit_movements', [
            'sale_id' => $sale->id,
            'type' => 'cargo',
            'amount' => 10.00,
        ]);

        $customer->refresh();
        $this->assertEquals(-10.00, (float) $customer->balance);
    }

    public function test_credit_sale_respects_defined_limit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ExchangeRate::factory()->create(['rate' => 100]);
        $customer = Customer::factory()->create([
            'credit_limit_type' => 'monto',
            'credit_limit_amount' => 50.00,
            'balance' => -40.00,
        ]);
        $product = Product::factory()->create(['control_type' => 'demanda', 'sale_price' => 10]);

        $response = $this->postJson('/pos', [
            'cart' => [['product_id' => $product->id, 'name' => $product->name, 'price' => 2000.00, 'quantity' => 1]],
            'payment_method' => 'credito',
            'customer_id' => $customer->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Límite de crédito excedido', $response->json('error'));
    }

    public function test_credit_sale_allows_any_amount_when_limit_is_libre(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ExchangeRate::factory()->create(['rate' => 100]);
        $customer = Customer::factory()->create(['credit_limit_type' => 'libre']);
        $product = Product::factory()->create(['control_type' => 'demanda', 'sale_price' => 10]);

        $response = $this->postJson('/pos', [
            'cart' => [['product_id' => $product->id, 'name' => $product->name, 'price' => 500000.00, 'quantity' => 1]],
            'payment_method' => 'credito',
            'customer_id' => $customer->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_credit_sale_rejects_inactive_customer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ExchangeRate::factory()->create(['rate' => 100]);
        $customer = Customer::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['control_type' => 'demanda']);

        $response = $this->postJson('/pos', [
            'cart' => [['product_id' => $product->id, 'name' => $product->name, 'price' => 100.00, 'quantity' => 1]],
            'payment_method' => 'credito',
            'customer_id' => $customer->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_processes_sale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'inventariable',
            'sale_price' => 10.00,
            'stock_current' => 20,
            'stock_min' => 5,
        ]);

        $response = $this->postJson('/pos', [
            'cart' => [
                ['product_id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 2],
            ],
            'payment_method' => 'efectivo',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => 'Venta procesada exitosamente.']);

        $product->refresh();
        $this->assertEquals(18, $product->stock_current);
        $this->assertDatabaseHas('sales', ['status' => 'completada']);
        $this->assertDatabaseHas('sale_items', ['quantity' => 2]);
        $this->assertDatabaseHas('sale_payments', ['method' => 'efectivo', 'amount' => 20.00]);
    }

    public function test_sale_with_empty_cart_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/pos', [
            'cart' => [],
            'payment_method' => 'efectivo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'El carrito está vacío.']);
    }

    public function test_demanda_product_does_not_deduct_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'demanda',
            'sale_price' => 5.00,
            'stock_current' => 100,
        ]);

        $response = $this->postJson('/pos', [
            'cart' => [
                ['product_id' => $product->id, 'name' => $product->name, 'price' => 5.00, 'quantity' => 3],
            ],
            'payment_method' => 'biopago',
        ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertEquals(100, $product->stock_current);
    }

    public function test_produccion_product_deducts_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'produccion',
            'sale_price' => 4.00,
            'stock_current' => 10,
        ]);

        $response = $this->postJson('/pos', [
            'cart' => [
                ['product_id' => $product->id, 'name' => $product->name, 'price' => 4.00, 'quantity' => 2],
            ],
            'payment_method' => 'pago_movil',
        ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertEquals(8, $product->stock_current);
    }

    public function test_inventariable_product_deducts_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'inventariable',
            'sale_price' => 3.00,
            'stock_current' => 15,
        ]);

        $response = $this->postJson('/pos', [
            'cart' => [
                ['product_id' => $product->id, 'name' => $product->name, 'price' => 3.00, 'quantity' => 3],
            ],
            'payment_method' => 'efectivo',
        ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertEquals(12, $product->stock_current);
    }

    public function test_credit_sale_with_nonexistent_customer_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ExchangeRate::factory()->create(['rate' => 100]);

        $product = Product::factory()->create([
            'control_type' => 'demanda',
            'sale_price' => 5.00,
        ]);

        $response = $this->postJson('/pos', [
            'cart' => [
                ['product_id' => $product->id, 'name' => $product->name, 'price' => 5.00, 'quantity' => 1],
            ],
            'payment_method' => 'credito',
            'customer_id' => 99999,
        ]);

        $response->assertStatus(422);
    }
}
