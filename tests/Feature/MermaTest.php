<?php

namespace Tests\Feature;

use App\Models\InventoryAdjustment;
use App\Models\Merma;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MermaTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/mermas');

        $response->assertStatus(200);
        $response->assertViewIs('mermas.index');
    }

    public function test_shows_empty_state(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/mermas');

        $response->assertStatus(200);
        $response->assertSee('No hay registros de salidas.');
    }

    public function test_stores_merma(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 20]);

        $response = $this->post('/mermas', [
            'type' => 'merma',
            'reason' => 'vencido',
            'notes' => 'Producto caducado',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('mermas', [
            'product_id' => $product->id,
            'quantity' => 3,
            'reason' => 'vencido',
            'type' => 'merma',
            'cost' => null,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('inventory_adjustments', [
            'product_id' => $product->id,
            'type' => 'salida',
            'reason' => 'merma',
            'notes' => 'Merma: vencido',
        ]);
        $product->refresh();
        $this->assertEquals(17, $product->stock_current);
    }

    public function test_stores_consumption_with_cost(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 10, 'cost_price' => 2.50]);

        $response = $this->post('/mermas', [
            'type' => 'consumo',
            'reason' => 'otro',
            'notes' => 'Dueño lo consumió',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 2, 'cost' => '2.50'],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('mermas', [
            'product_id' => $product->id,
            'quantity' => 2,
            'reason' => 'otro',
            'type' => 'consumo',
            'cost' => 2.50,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('inventory_adjustments', [
            'product_id' => $product->id,
            'type' => 'salida',
            'reason' => 'merma',
            'notes' => 'Consumo interno: otro',
        ]);
        $product->refresh();
        $this->assertEquals(8, $product->stock_current);
    }

    public function test_consumption_defaults_cost_to_product_cost(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 5, 'cost_price' => 4.00]);

        $this->post('/mermas', [
            'type' => 'consumo',
            'reason' => 'otro',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->assertDatabaseHas('mermas', [
            'product_id' => $product->id,
            'type' => 'consumo',
            'cost' => 4.00,
        ]);
    }

    public function test_consumption_with_multiple_products(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $perro = Product::factory()->create(['name' => 'Perro Caliente', 'stock_current' => 20, 'cost_price' => 1.00]);
        $refresco = Product::factory()->create(['name' => 'Refresco', 'stock_current' => 30, 'cost_price' => 0.80]);
        $hamburguesa = Product::factory()->create(['name' => 'Hamburguesa', 'stock_current' => 10, 'cost_price' => 3.00]);

        $response = $this->post('/mermas', [
            'type' => 'consumo',
            'reason' => 'otro',
            'notes' => 'Dueño se comió 2 perros y un refresco',
            'lines' => [
                ['product_id' => $perro->id, 'quantity' => 2, 'cost' => '1.00'],
                ['product_id' => $refresco->id, 'quantity' => 1, 'cost' => '0.80'],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('mermas', [
            'product_id' => $perro->id,
            'quantity' => 2,
            'type' => 'consumo',
            'cost' => 1.00,
        ]);
        $this->assertDatabaseHas('mermas', [
            'product_id' => $refresco->id,
            'quantity' => 1,
            'type' => 'consumo',
            'cost' => 0.80,
        ]);
        $this->assertDatabaseMissing('mermas', ['product_id' => $hamburguesa->id]);

        $perro->refresh();
        $refresco->refresh();
        $this->assertEquals(18, $perro->stock_current);
        $this->assertEquals(29, $refresco->stock_current);
        $this->assertEquals(2, Merma::count());
        $this->assertEquals(2, InventoryAdjustment::count());
    }

    public function test_multi_line_rejects_if_one_exceeds_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $perro = Product::factory()->create(['name' => 'Perro', 'stock_current' => 3, 'cost_price' => 1.00]);
        $refresco = Product::factory()->create(['name' => 'Refresco', 'stock_current' => 5, 'cost_price' => 0.80]);

        $response = $this->post('/mermas', [
            'type' => 'consumo',
            'reason' => 'otro',
            'lines' => [
                ['product_id' => $perro->id, 'quantity' => 2],
                ['product_id' => $refresco->id, 'quantity' => 99],
            ],
        ]);

        $response->assertSessionHasErrors('lines.1.quantity');
        $this->assertDatabaseCount('mermas', 0);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $perro->refresh();
        $refresco->refresh();
        $this->assertEquals(3, $perro->stock_current);
        $this->assertEquals(5, $refresco->stock_current);
    }

    public function test_rejects_consumption_with_merma_reason(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 10]);

        $response = $this->post('/mermas', [
            'type' => 'consumo',
            'reason' => 'vencido',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('mermas', 0);
    }

    public function test_validates_type_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create();

        $response = $this->post('/mermas', [
            'reason' => 'vencido',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('mermas', 0);
    }

    public function test_validates_lines_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/mermas', [
            'type' => 'merma',
            'reason' => 'vencido',
            'lines' => [],
        ]);

        $response->assertSessionHasErrors('lines');
    }

    public function test_validates_line_quantity_min(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create();

        $response = $this->post('/mermas', [
            'type' => 'merma',
            'reason' => 'danado',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.quantity');
    }

    public function test_shows_merma_list(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['name' => 'Refresco']);
        Merma::factory()->create(['product_id' => $product->id, 'quantity' => 5, 'reason' => 'vencido', 'type' => 'merma']);

        $response = $this->get('/mermas');

        $response->assertStatus(200);
        $response->assertSee('Refresco');
        $response->assertSee('-5');
        $response->assertSee('Vencido');
        $response->assertSee('Consumo interno hoy');
    }

    public function test_list_shows_consumption_cost(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['name' => 'Perro']);
        Merma::factory()->consumption()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'cost' => 1.25,
        ]);

        $response = $this->get('/mermas');

        $response->assertStatus(200);
        $response->assertSee('USD 2,50');
        $response->assertSee('Consumo del dueño');
    }

    public function test_filter_by_type_consumo(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['name' => 'Cafe']);
        Merma::factory()->create(['product_id' => $product->id, 'reason' => 'vencido', 'type' => 'merma']);
        Merma::factory()->consumption()->create(['product_id' => $product->id]);

        $response = $this->get('/mermas?type=consumo');

        $response->assertStatus(200);
        $data = $response->viewData('mermas');
        $this->assertEquals(1, $data->total());
        $this->assertEquals('consumo', $data->first()->type);
    }

    public function test_consumption_has_correct_labels_and_subtotal(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['name' => 'Pizza']);
        $m = Merma::factory()->consumption()->create(['product_id' => $product->id, 'quantity' => 2, 'cost' => 3.00]);

        $this->assertTrue($m->isConsumption());
        $this->assertEquals('Consumo interno', $m->type_label);
        $this->assertEquals('info', $m->type_badge);
        $this->assertEquals('Consumo del dueño', $m->reason_label);
        $this->assertEquals(6.00, $m->subtotal());
    }

    public function test_rejects_merma_exceeding_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 3]);

        $response = $this->post('/mermas', [
            'type' => 'merma',
            'reason' => 'danado',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.quantity');
        $this->assertDatabaseCount('mermas', 0);
        $product->refresh();
        $this->assertEquals(3, $product->stock_current);
    }

    public function test_store_rejects_nonexistent_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/mermas', [
            'type' => 'merma',
            'reason' => 'danado',
            'lines' => [
                ['product_id' => 99999, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.product_id');
        $this->assertDatabaseCount('mermas', 0);
    }
}
