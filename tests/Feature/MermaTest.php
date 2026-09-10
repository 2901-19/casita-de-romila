<?php

namespace Tests\Feature;

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
            'product_id' => $product->id,
            'quantity' => 3,
            'type' => 'merma',
            'reason' => 'vencido',
            'notes' => 'Producto caducado',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('mermas', [
            'product_id' => $product->id,
            'quantity' => 3,
            'reason' => 'vencido',
            'type' => 'merma',
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

    public function test_stores_consumption(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 10]);

        $response = $this->post('/mermas', [
            'product_id' => $product->id,
            'quantity' => 2,
            'type' => 'consumo',
            'reason' => 'otro',
            'notes' => 'Dueño lo consumió',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('mermas', [
            'product_id' => $product->id,
            'quantity' => 2,
            'reason' => 'otro',
            'type' => 'consumo',
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

    public function test_rejects_consumption_with_merma_reason(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 10]);

        $response = $this->post('/mermas', [
            'product_id' => $product->id,
            'quantity' => 2,
            'type' => 'consumo',
            'reason' => 'vencido',
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
            'product_id' => $product->id,
            'quantity' => 1,
            'reason' => 'vencido',
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('mermas', 0);
    }

    public function test_validates_quantity_min(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create();

        $response = $this->post('/mermas', [
            'product_id' => $product->id,
            'quantity' => 0,
            'type' => 'merma',
            'reason' => 'danado',
        ]);

        $response->assertSessionHasErrors('quantity');
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

    public function test_consumption_has_correct_labels(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['name' => 'Pizza']);
        $m = Merma::factory()->consumption()->create(['product_id' => $product->id]);

        $this->assertTrue($m->isConsumption());
        $this->assertEquals('Consumo interno', $m->type_label);
        $this->assertEquals('info', $m->type_badge);
        $this->assertEquals('Consumo del dueño', $m->reason_label);
    }

    public function test_rejects_merma_exceeding_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 3]);

        $response = $this->post('/mermas', [
            'product_id' => $product->id,
            'quantity' => 5,
            'type' => 'merma',
            'reason' => 'danado',
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('mermas', 0);
        $product->refresh();
        $this->assertEquals(3, $product->stock_current);
    }

    public function test_store_rejects_nonexistent_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/mermas', [
            'product_id' => 99999,
            'quantity' => 1,
            'type' => 'merma',
            'reason' => 'danado',
        ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('mermas', 0);
    }
}
