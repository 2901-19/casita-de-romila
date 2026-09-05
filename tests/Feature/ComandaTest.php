<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\ComandaPayment;
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
        foreach ($items as $entry) {
            [$product, $qty] = $entry;
            $cart[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'order_type' => $entry[2] ?? ComandaItem::ORDER_LOCAL,
                'note' => $entry[3] ?? null,
            ];
        }
        return ['cart' => $cart];
    }

    private function collectComanda(int $comandaId, string $method = 'efectivo', ?int $customerId = null)
    {
        return $this->post("/comandas/{$comandaId}/cobrar", [
            'payment_method' => $method,
            'customer_id' => $customerId,
        ]);
    }

    private function closeComanda(int $comandaId)
    {
        return $this->post("/comandas/{$comandaId}/cerrar");
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

        $response = $this->post('/comandas', $this->makeCart([[$product, 2]]));

        $response->assertRedirect();

        $comanda = Comanda::first();
        $this->assertEquals('montada', $comanda->status);
        $this->assertEquals('0001', $comanda->comanda_number);
        // total = 10 * 100 (rate) * 2
        $this->assertEquals(2000.00, (float) $comanda->total);
        $this->assertCount(1, $comanda->items);
        $this->assertEquals('local', $comanda->items->first()->order_type);
        $this->assertFalse($comanda->items->first()->collected);
        // no stock deducted on creation
        $product->refresh();
        $this->assertEquals(50, $product->stock_current);
    }

    public function test_comanda_number_increments_within_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $this->post('/comandas', $this->makeCart([[$product, 1]]));

        $this->assertEquals('0001', Comanda::orderBy('id')->first()->comanda_number);
        $this->assertEquals('0002', Comanda::orderBy('id')->skip(1)->first()->comanda_number);
    }

    public function test_show_displays_total_always(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 3, 'para_llevar']]));
        $comanda = Comanda::first();

        $response = $this->get("/comandas/{$comanda->id}");

        $response->assertStatus(200);
        $response->assertSee('Bs 3.000,00');
    }

    public function test_store_delivery_keeps_customer_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $response = $this->post('/comandas', $this->makeCart([[$product, 1, 'delivery']]) + ['customer_name' => 'Pedro']);

        $comanda = Comanda::with('items')->first();
        $this->assertEquals('montada', $comanda->status);
        $this->assertEquals('Pedro', $comanda->customer_name);
        $this->assertTrue($comanda->hasDeliveryItems());
        $this->assertEquals('delivery', $comanda->items->first()->order_type);
    }

    public function test_demand_items_get_separate_lines(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $demanda = $this->makeProduct(type: 'demanda', price: 10);

        $this->post('/comandas', $this->makeCart([[$demanda, 1], [$demanda, 1]]));

        $comanda = Comanda::with('items')->first();
        $this->assertCount(2, $comanda->items);
        $this->assertTrue($comanda->items->every(fn ($i) => $i->quantity === 1));
        // 2 * 1000 (rate)
        $this->assertEquals(2000.00, (float) $comanda->total);
    }

    public function test_per_item_note_is_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1, 'local', 'Sin cebolla']]));

        $comanda = Comanda::with('items')->first();
        $this->assertEquals('Sin cebolla', $comanda->items->first()->note);
    }

    public function test_mixed_types_on_one_comanda(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $local = $this->makeProduct();
        $delivery = $this->makeProduct(price: 5);

        $this->post('/comandas', $this->makeCart([[$local, 1, 'local'], [$delivery, 1, 'delivery']]));

        $comanda = Comanda::with('items')->first();
        $this->assertEquals(2, $comanda->items->count());
        $this->assertTrue($comanda->hasDeliveryItems());
    }

    public function test_collect_allowed_at_any_moment_without_venting_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct(stock: 50, type: 'inventariable', price: 10);
        $this->post('/comandas', $this->makeCart([[$product, 2]]));
        $comanda = Comanda::with('items')->first();

        // No es necesario entregar: se cobra desde montada
        $response = $this->collectComanda($comanda->id, 'efectivo');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $comanda->refresh();
        $this->assertEquals('montada', $comanda->status);
        $this->assertNull($comanda->sale_id);
        $this->assertTrue($comanda->items->every(fn ($i) => $i->collected));
        $this->assertEquals(1, ComandaPayment::count());
        $this->assertEquals(2000.00, (float) ComandaPayment::first()->amount);

        // El stock NO se descuenta al cobrar, solo al cerrar
        $product->refresh();
        $this->assertEquals(50, $product->stock_current);
    }

    public function test_collect_partial_after_adding_new_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $extra = $this->makeProduct(price: 5);

        // Pedido inicial y cobro de una vez
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $comanda = Comanda::first();
        $this->collectComanda($comanda->id, 'efectivo');

        $comanda->refresh();
        $this->assertEquals(1000.00, (float) $comanda->collectedTotal());

        // Se agrega algo nuevo después del primer cobro
        $this->put("/comandas/{$comanda->id}", $this->makeCart([[$extra, 1]]));

        $comanda->refresh();
        $this->assertCount(2, $comanda->items);
        $this->assertEquals(1500.00, (float) $comanda->total);
        // Solo el item nuevo queda pendiente
        $this->assertEquals(1, $comanda->items->where('collected', true)->count());
        $this->assertEquals(1, $comanda->items->where('collected', false)->count());

        // Se cobra solo el extra
        $response = $this->collectComanda($comanda->id, 'biopago');
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $comanda->refresh();
        $this->assertTrue($comanda->isFullyCollected());
        $this->assertEquals(2, ComandaPayment::count());
    }

    public function test_close_creates_sale_with_grouped_payments_and_deducts_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct(stock: 50, type: 'inventariable', price: 10);
        $this->post('/comandas', $this->makeCart([[$product, 2]]));
        $comanda = Comanda::first();

        $this->collectComanda($comanda->id, 'efectivo');
        $response = $this->closeComanda($comanda->id);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $comanda->refresh();
        $this->assertEquals('cobrada', $comanda->status);
        $this->assertNotNull($comanda->sale_id);

        $sale = Sale::find($comanda->sale_id);
        $this->assertEquals('completada', $sale->status);
        $this->assertEquals(2000.00, (float) $sale->total);
        $this->assertNotNull($sale->sale_number);
        $this->assertEquals(1, $sale->payments()->count());
        $this->assertEquals(2000.00, (float) $sale->payments()->first()->amount);

        $product->refresh();
        $this->assertEquals(48, $product->stock_current);
        $this->assertEquals(1, $sale->items()->count());
    }

    public function test_close_with_multiple_methods_creates_one_payment_per_method(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct(price: 10);
        $extra = $this->makeProduct(price: 5);

        // Dos cobros con métodos distintos (efectivo + biopago)
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $comanda = Comanda::first();
        $this->collectComanda($comanda->id, 'efectivo');
        $this->put("/comandas/{$comanda->id}", $this->makeCart([[$extra, 1]]));
        $comanda->refresh();
        $this->collectComanda($comanda->id, 'biopago');

        $this->closeComanda($comanda->id);

        $sale = Sale::find($comanda->fresh()->sale_id);
        $payments = $sale->payments()->get();
        $this->assertCount(2, $payments);
        $this->assertTrue($payments->contains('method', 'efectivo'));
        $this->assertTrue($payments->contains('method', 'biopago'));
        $this->assertEquals(1000.00, (float) $payments->firstWhere('method', 'efectivo')->amount);
        $this->assertEquals(500.00, (float) $payments->firstWhere('method', 'biopago')->amount);
    }

    public function test_close_requires_full_collection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $comanda = Comanda::first();
        $this->put("/comandas/{$comanda->id}", $this->makeCart([[$product, 1]]));

        $comanda->refresh();
        // Se agrega un item nuevo sin cobrar
        $extra = $this->makeProduct(price: 5);
        $this->put("/comandas/{$comanda->id}", $this->makeCart([[$product, 1], [$extra, 1]]));
        $comanda->refresh();
        $this->assertFalse($comanda->isFullyCollected());

        $response = $this->closeComanda($comanda->id);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $comanda->refresh();
        $this->assertEquals('montada', $comanda->status);
        $this->assertNull($comanda->sale_id);
    }

    public function test_close_with_credit_creates_credit_charge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['credit_limit_type' => 'libre']);
        $product = $this->makeProduct(stock: 50, type: 'inventariable', price: 10);
        $this->post('/comandas', $this->makeCart([[$product, 2]]));
        $comanda = Comanda::first();

        $this->collectComanda($comanda->id, 'credito', $customer->id);
        $comanda->refresh();
        $this->assertTrue($comanda->isFullyCollected());

        $this->closeComanda($comanda->id);

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

    public function test_cannot_mix_credit_with_cash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['credit_limit_type' => 'libre']);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 2]]));
        $comanda = Comanda::first();

        $this->collectComanda($comanda->id, 'efectivo');

        $response = $this->collectComanda($comanda->id, 'credito', $customer->id);
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $comanda->refresh();
        $this->assertEquals(1, ComandaPayment::count());
        $this->assertEquals('efectivo', ComandaPayment::first()->method);
    }

    public function test_cannot_add_cash_after_credit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['credit_limit_type' => 'libre']);
        $product = $this->makeProduct();
        $extra = $this->makeProduct(price: 5);
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $comanda = Comanda::first();

        $this->collectComanda($comanda->id, 'credito', $customer->id);
        $this->put("/comandas/{$comanda->id}", $this->makeCart([[$extra, 1]]));
        $comanda->refresh();

        $response = $this->collectComanda($comanda->id, 'efectivo');
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $comanda->refresh();
        $this->assertEquals(1, ComandaPayment::count());
        $this->assertEquals('credito', ComandaPayment::first()->method);
    }

    public function test_edit_preserves_collected_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $extra = $this->makeProduct(price: 5);
        $this->post('/comandas', $this->makeCart([[$product, 2]]));
        $comanda = Comanda::first();

        $this->collectComanda($comanda->id, 'efectivo');
        $comanda->refresh();
        $this->assertTrue($comanda->items->first()->collected);

        // El item cobrado se mantiene aunque el cart solo traiga el nuevo
        $response = $this->put("/comandas/{$comanda->id}", $this->makeCart([[$extra, 1]]));

        $response->assertRedirect();
        $comanda->refresh();
        $this->assertCount(2, $comanda->items);
        $collected = $comanda->items->firstWhere('product_id', $product->id);
        $this->assertEquals(2, $collected->quantity);
        $this->assertTrue($collected->collected);
        // 2 collect units from the collected item are preserved in total
        $this->assertEquals(2500.00, (float) $comanda->total);
    }

    public function test_deliver_item_one_by_one_and_auto_entregada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 2]]));
        $comanda = Comanda::with('items')->first();
        $item = $comanda->items->first();

        $this->patch("/comandas/{$comanda->id}/items/{$item->id}/entregar");

        $item->refresh();
        $comanda->refresh();
        $this->assertEquals(1, $item->delivered_quantity);
        $this->assertEquals('montada', $comanda->status);

        $this->patch("/comandas/{$comanda->id}/items/{$item->id}/entregar");

        $item->refresh();
        $comanda->refresh();
        $this->assertEquals(2, $item->delivered_quantity);
        $this->assertEquals('entregada', $comanda->status);
    }

    public function test_mark_delivered_shortcut_marks_all_and_estado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 3]]));
        $comanda = Comanda::with('items')->first();

        $this->patch("/comandas/{$comanda->id}/entregar");

        $comanda->refresh();
        $this->assertEquals('entregada', $comanda->status);
        $this->assertTrue($comanda->items->every(fn ($i) => $i->delivered_quantity === $i->quantity));
    }

    public function test_cannot_edit_after_cobrada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $comanda = Comanda::first();
        $this->collectComanda($comanda->id, 'efectivo');
        $this->closeComanda($comanda->id);
        $comanda->refresh();
        $this->assertEquals('cobrada', $comanda->status);

        $response = $this->put("/comandas/{$comanda->id}", $this->makeCart([[$product, 10]]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $comanda->refresh();
        $this->assertCount(1, $comanda->items);
        $this->assertEquals(1, $comanda->items->first()->quantity);
    }

    public function test_index_filters_by_item_order_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1, 'delivery']]) + ['customer_name' => 'Ana']);
        $this->post('/comandas', $this->makeCart([[$product, 1, 'local']]));

        $response = $this->get('/comandas?order_type=delivery');

        $data = $response->viewData('comandas');
        $this->assertEquals(1, $data->total());
        $response->assertSee('#0001');
    }

    public function test_sale_number_is_unique_global(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $c1 = Comanda::first();
        $this->collectComanda($c1->id, 'efectivo');
        $this->closeComanda($c1->id);

        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $c2 = Comanda::orderBy('id')->skip(1)->first();
        $this->collectComanda($c2->id, 'efectivo');
        $this->closeComanda($c2->id);

        $s1 = Sale::find($c1->fresh()->sale_id);
        $s2 = Sale::find($c2->fresh()->sale_id);
        $this->assertNotNull($s1);
        $this->assertNotNull($s2);
        $this->assertNotEquals($s1->sale_number, $s2->sale_number);
    }

    public function test_store_applies_round_bs_on_product_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'inventariable',
            'sale_price' => 10.17,
            'stock_current' => 50,
            'is_active' => true,
            'round_bs' => 5,
        ]);

        $this->post('/comandas', $this->makeCart([[$product, 2]]));

        $comanda = Comanda::first();
        $this->assertEquals(2040.00, (float) $comanda->total);
        $this->assertEquals(1020.00, (float) $comanda->items->first()->unit_price);
    }

    public function test_store_applies_round_bs_on_combo_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $combo = \App\Models\Combo::factory()->create([
            'sale_price' => 8.32,
            'is_active' => true,
            'round_bs' => 10,
        ]);

        $cart = [
            'cart' => [[
                'product_id' => 'combo_'.$combo->id,
                'quantity' => 1,
                'order_type' => ComandaItem::ORDER_LOCAL,
                'note' => null,
            ]],
        ];

        $this->post('/comandas', $cart);

        $comanda = Comanda::first();
        $this->assertEquals(840.00, (float) $comanda->total);
        $this->assertEquals(840.00, (float) $comanda->items->first()->unit_price);
    }

    public function test_create_page_rounds_catalog_prices(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Product::factory()->create([
            'name' => 'Nacolid',
            'control_type' => 'inventariable',
            'sale_price' => 10.17,
            'is_active' => true,
            'round_bs' => 5,
        ]);

        $response = $this->get('/comandas/create');

        $response->assertStatus(200);
        $response->assertSee('1020.00', false);
    }

    public function test_store_redirects_to_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $response = $this->post('/comandas', $this->makeCart([[$product, 1]]));

        $response->assertRedirect(route('comandas.index'));
        $response->assertSessionHas('success');
    }

    public function test_close_redirects_to_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $comanda = Comanda::first();

        $this->collectComanda($comanda->id, 'efectivo');
        $response = $this->closeComanda($comanda->id);

        $response->assertRedirect(route('comandas.index'));
        $response->assertSessionHas('success');
    }

    public function test_index_excludes_cobrada_comandas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $this->patch('/comandas/' . Comanda::orderBy('id')->skip(1)->first()->id . '/entregar');
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $this->collectComanda(Comanda::orderBy('id')->skip(2)->first()->id, 'efectivo');
        $this->closeComanda(Comanda::orderBy('id')->skip(2)->first()->id);

        $response = $this->get('/comandas');

        $response->assertStatus(200);
        $response->assertSee('#0001');
        $response->assertSee('#0002');
        $response->assertDontSee('#0003');
        $this->assertEquals(2, $response->viewData('comandas')->total());
    }

    public function test_history_shows_cobrada_comandas_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $c1 = Comanda::first();
        $this->post('/comandas', $this->makeCart([[$product, 1]]));
        $c2 = Comanda::orderBy('id')->skip(1)->first();
        $this->collectComanda($c2->id, 'efectivo');
        $this->closeComanda($c2->id);

        $response = $this->get('/comandas/history');

        $response->assertStatus(200);
        $response->assertViewIs('comandas.index');
        $response->assertSee('#0002');
        $response->assertDontSee('#0001');
        $this->assertEquals(1, $response->viewData('comandas')->total());
        $this->assertEquals('history', $response->viewData('scope'));
    }

    public function test_index_has_historial_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/comandas');

        $response->assertStatus(200);
        $response->assertSee(route('comandas.history'));
    }

    public function test_status_filter_only_lists_active_statuses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();
        $this->post('/comandas', $this->makeCart([[$product, 1, 'local']]));
        $this->post('/comandas', $this->makeCart([[$product, 1, 'delivery']]));
        $this->patch('/comandas/' . Comanda::orderBy('id')->skip(1)->first()->id . '/entregar');

        $response = $this->get('/comandas?status=entregada');

        $data = $response->viewData('comandas');
        $this->assertEquals(1, $data->total());
    }
}