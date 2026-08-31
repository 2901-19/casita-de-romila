<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comanda;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComandaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ExchangeRate::factory()->create(['rate' => 100]);
    }

    private function makeProduct($stock = 50, $type = 'inventariable', $price = 10): Product
    {
        return Product::factory()->create([
            'control_type' => $type,
            'stock_current' => $stock,
            'sale_price' => $price,
            'is_active' => true,
        ]);
    }

    private function makeCart(array $items): array
    {
        $cart = [];
        foreach ($items as [$product, $qty]) {
            $cart[] = ['product_id' => $product->id, 'quantity' => $qty];
        }
        return ['cart' => $cart];
    }

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/comandas');

        $response->assertStatus(200);
        $response->assertViewIs('comandas.index');
    }

    public function test_create_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/comandas/create');

        $response->assertStatus(200);
        $response->assertViewIs('comandas.create');
    }

    public function test_store_creates_montada_comanda_with_daily_number(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $response = $this->post('/comandas', $this->makeCart([[$product, 2]]) + ['order_type' => 'local']);

        $response->assertRedirect();

        $comanda = Comanda::first();
        $this->assertEquals('montada', $comanda->status);
        $this->assertEquals('0001', $comanda->comanda_number);
        $this->assertEquals('local', $comanda->order_type);
        // total = 10 * 100 (rate) * 2
        $this->assertEquals(2000.00, (float) $comanda->total);
        $this->assertCount(1, $comanda->items);
        // no stock deducted on creation
        $product->refresh();
        $this->assertEquals(50, $product->stock_current);
    }

    public function test_comanda_number_increments_within_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1]]) + ['order_type' => 'local']);
        $this->post('/comandas', $this->makeCart([[$product, 1]]) + ['order_type' => 'local']);

        $this->assertEquals('0001', Comanda::orderBy('id')->first()->comanda_number);
        $this->assertEquals('0002', Comanda::orderBy('id')->skip(1)->first()->comanda_number);
    }

    public function test_show_displays_total_always(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 3]]) + ['order_type' => 'para_llevar']);
        $comanda = Comanda::first();

        $response = $this->get("/comandas/{$comanda->id}");

        $response->assertStatus(200);
        $response->assertSee('Bs 3.000,00');
    }

    public function test_store_delivery_keeps_montada_and_pending_payment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $response = $this->post('/comandas', $this->makeCart([[$product, 1]]) + ['order_type' => 'delivery', 'customer_name' => 'Pedro']);

        $comanda = Comanda::first();
        $this->assertEquals('montada', $comanda->status);
        $this->assertEquals('Pedro', $comanda->customer_name);
        $this->assertTrue($comanda->is_delivery);
    }

    public function test_cannot_collect_local_when_not_entregada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 1]]) + ['order_type' => 'local']);
        $comanda = Comanda::first();

        $response = $this->post("/comandas/{$comanda->id}/cobrar", ['payment_method' => 'efectivo']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $comanda->refresh();
        $this->assertEquals('montada', $comanda->status);
        $this->assertNull($comanda->sale_id);
    }

    public function test_deliver_item_one_by_one_and_auto_entregada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 2]]) + ['order_type' => 'local']);
        $comanda = Comanda::with('items')->first();
        $item = $comanda->items->first();

        // deliver 1 of 2 units -> still not fully delivered
        $this->patch("/comandas/{$comanda->id}/items/{$item->id}/entregar");

        $item->refresh();
        $comanda->refresh();
        $this->assertEquals(1, $item->delivered_quantity);
        $this->assertEquals('montada', $comanda->status);

        // deliver remaining unit -> automatically entregada
        $this->patch("/comandas/{$comanda->id}/items/{$item->id}/entregar");

        $item->refresh();
        $comanda->refresh();
        $this->assertEquals(2, $item->delivered_quantity);
        $this->assertEquals('entregada', $comanda->status);
    }

    public function test_mixed_comanda_not_entregada_until_all_delivered(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 5]]) + ['order_type' => 'local']);
        $comanda = Comanda::with('items')->first();
        $item = $comanda->items->first();

        // scenario: 3 eaten here delivered, 2 to-go pending
        for ($i = 0; $i < 3; $i++) {
            $this->patch("/comandas/{$comanda->id}/items/{$item->id}/entregar");
        }

        $item->refresh();
        $comanda->refresh();
        $this->assertEquals(3, $item->delivered_quantity);
        $this->assertEquals('montada', $comanda->status);

        // remaining 2 delivered -> entregada
        for ($i = 0; $i < 2; $i++) {
            $this->patch("/comandas/{$comanda->id}/items/{$item->id}/entregar");
        }

        $item->refresh();
        $comanda->refresh();
        $this->assertEquals(5, $item->delivered_quantity);
        $this->assertEquals('entregada', $comanda->status);
    }

    public function test_mark_delivered_shortcut_marks_all_and_estado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 3]]) + ['order_type' => 'local']);
        $comanda = Comanda::with('items')->first();

        $this->patch("/comandas/{$comanda->id}/entregar");

        $comanda->refresh();
        $this->assertEquals('entregada', $comanda->status);
        $this->assertTrue($comanda->items->every(fn ($i) => $i->delivered_quantity === $i->quantity));
    }

    public function test_collect_local_after_entregada_deducts_stock_and_creates_sale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct(stock: 50, type: 'inventariable', price: 10);
        $this->post('/comandas', $this->makeCart([[$product, 2]]) + ['order_type' => 'local']);
        $comanda = Comanda::with('items')->first();
        $this->patch("/comandas/{$comanda->id}/entregar");

        $response = $this->post("/comandas/{$comanda->id}/cobrar", ['payment_method' => 'efectivo']);

        $response->assertRedirect();
        $comanda->refresh();
        $this->assertEquals('cobrada', $comanda->status);
        $this->assertNotNull($comanda->sale_id);

        $sale = Sale::find($comanda->sale_id);
        $this->assertEquals('completada', $sale->status);
        $this->assertEquals(2000.00, (float) $sale->total);
        $this->assertNotNull($sale->sale_number);
        $this->assertEquals(1, $sale->payments()->count());

        $product->refresh();
        $this->assertEquals(48, $product->stock_current);
        $this->assertEquals(1, $sale->items()->count());
    }

    public function test_collect_delivery_from_montada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct(stock: 50, type: 'produccion', price: 5);
        $this->post('/comandas', $this->makeCart([[$product, 4]]) + ['order_type' => 'delivery', 'customer_name' => 'Ana']);
        $comanda = Comanda::first();

        $response = $this->post("/comandas/{$comanda->id}/cobrar", ['payment_method' => 'biopago']);

        $response->assertRedirect();
        $comanda->refresh();
        $this->assertEquals('cobrada', $comanda->status);
        $this->assertNotNull($comanda->sale_id);
        $product->refresh();
        $this->assertEquals(46, $product->stock_current);
    }

    public function test_collect_with_credit_creates_credit_charge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['credit_limit_type' => 'libre']);
        $product = $this->makeProduct(stock: 50, type: 'inventariable', price: 10);
        $this->post('/comandas', $this->makeCart([[$product, 2]]) + ['order_type' => 'local']);
        $comanda = Comanda::first();
        $this->patch("/comandas/{$comanda->id}/entregar");

        $response = $this->post("/comandas/{$comanda->id}/cobrar", [
            'payment_method' => 'credito',
            'customer_id' => $customer->id,
        ]);

        $response->assertRedirect();
        $comanda->refresh();
        $this->assertEquals('cobrada', $comanda->status);

        $sale = Sale::find($comanda->sale_id);
        $this->assertEquals('pendiente', $sale->status);
        $this->assertEquals($customer->id, $sale->customer_id);
        $this->assertDatabaseHas('credit_movements', [
            'sale_id' => $sale->id,
            'type' => 'cargo',
            'amount' => 20.00,
        ]);
        $customer->refresh();
        $this->assertEquals(-20.00, (float) $customer->balance);
    }

    public function test_edit_comanda_while_not_cobrada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $extra = $this->makeProduct(price: 5);
        $this->post('/comandas', $this->makeCart([[$product, 2]]) + ['order_type' => 'local']);
        $comanda = Comanda::first();

        // add extra product
        $response = $this->put("/comandas/{$comanda->id}", $this->makeCart([[$product, 2], [$extra, 1]]) + ['order_type' => 'local']);

        $response->assertRedirect();
        $comanda->refresh();
        $this->assertCount(2, $comanda->items);
        // 2 * 1000 + 1 * 500
        $this->assertEquals(2500.00, (float) $comanda->total);
    }

    public function test_cannot_edit_after_cobrada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 1]]) + ['order_type' => 'local']);
        $comanda = Comanda::first();
        $this->patch("/comandas/{$comanda->id}/entregar");
        $this->post("/comandas/{$comanda->id}/cobrar", ['payment_method' => 'efectivo']);
        $comanda->refresh();
        $this->assertEquals('cobrada', $comanda->status);

        $response = $this->put("/comandas/{$comanda->id}", $this->makeCart([[$product, 10]]) + ['order_type' => 'local']);

        $response->assertRedirect();
        $comanda->refresh();
        $this->assertCount(1, $comanda->items);
        $this->assertEquals(1, $comanda->items->first()->quantity);
    }

    public function test_sale_number_is_unique_global(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1]]) + ['order_type' => 'delivery']);
        $c1 = Comanda::first();
        $this->post("/comandas/{$c1->id}/cobrar", ['payment_method' => 'efectivo']);

        $this->post('/comandas', $this->makeCart([[$product, 1]]) + ['order_type' => 'delivery']);
        $c2 = Comanda::orderBy('id')->skip(1)->first();
        $this->post("/comandas/{$c2->id}/cobrar", ['payment_method' => 'efectivo']);

        $s1 = Sale::find($c1->fresh()->sale_id);
        $s2 = Sale::find($c2->fresh()->sale_id);
        $this->assertNotNull($s1);
        $this->assertNotNull($s2);
        $this->assertNotEquals($s1->sale_number, $s2->sale_number);
    }
}
