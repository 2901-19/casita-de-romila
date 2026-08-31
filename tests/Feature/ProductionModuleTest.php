<?php

namespace Tests\Feature;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/productions');

        $response->assertStatus(200);
        $response->assertViewIs('productions.index');
    }

    public function test_shows_empty_state(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/productions');

        $response->assertStatus(200);
        $response->assertSee('No hay producción registrada');
    }

    public function test_stores_production(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'produccion',
            'stock_current' => 10,
        ]);

        $response = $this->post('/productions', [
            'product_id' => $product->id,
            'quantity' => 15,
            'notes' => 'Producción del día',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('productions', [
            'product_id' => $product->id,
            'quantity' => 15,
            'user_id' => $user->id,
        ]);
        $product->refresh();
        $this->assertEquals(25, $product->stock_current);
    }

    public function test_validates_quantity_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['control_type' => 'produccion']);

        $response = $this->post('/productions', [
            'product_id' => $product->id,
            'quantity' => '',
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_validates_quantity_min(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['control_type' => 'produccion']);

        $response = $this->post('/productions', [
            'product_id' => $product->id,
            'quantity' => 0,
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_shows_production_list(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['control_type' => 'produccion', 'name' => 'Torta']);
        Production::factory()->create(['product_id' => $product->id, 'quantity' => 20]);

        $response = $this->get('/productions');

        $response->assertStatus(200);
        $response->assertSee('Torta');
        $response->assertSee('+20');
    }

    public function test_filters_by_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product1 = Product::factory()->create(['control_type' => 'produccion', 'name' => 'Torta']);
        $product2 = Product::factory()->create(['control_type' => 'produccion', 'name' => 'Galleta']);
        Production::factory()->create(['product_id' => $product1->id]);
        Production::factory()->create(['product_id' => $product2->id]);

        $response = $this->get("/productions?product_id={$product1->id}");

        $response->assertStatus(200);
        $response->assertSee('Torta');
    }

    public function test_destroys_recent_production_and_restores_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'produccion',
            'stock_current' => 10,
        ]);

        $this->post('/productions', [
            'product_id' => $product->id,
            'quantity' => 15,
        ]);

        $production = Production::latest('id')->first();
        $this->assertDatabaseHas('inventory_adjustments', [
            'production_id' => $production->id,
            'reason' => 'produccion',
        ]);
        $this->assertEquals(25, $product->refresh()->stock_current);

        $response = $this->delete("/productions/{$production->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('productions', ['id' => $production->id]);
        $this->assertDatabaseMissing('inventory_adjustments', ['production_id' => $production->id]);
        $this->assertEquals(10, $product->refresh()->stock_current);
    }

    public function test_rejects_destroy_outside_undo_window(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'produccion',
            'stock_current' => 25,
        ]);
        $production = Production::factory()->create([
            'product_id' => $product->id,
            'quantity' => 15,
        ]);
        $production->forceFill(['created_at' => now()->subMinutes(21)])->save();
        InventoryAdjustment::factory()->create([
            'product_id' => $product->id,
            'production_id' => $production->id,
            'type' => 'entrada',
            'quantity' => 15,
            'reason' => 'produccion',
        ]);

        $response = $this->delete("/productions/{$production->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('productions', ['id' => $production->id]);
        $this->assertDatabaseHas('inventory_adjustments', ['production_id' => $production->id]);
        $this->assertEquals(25, $product->refresh()->stock_current);
    }

    public function test_rejects_destroy_with_insufficient_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'produccion',
            'stock_current' => 5,
        ]);
        $production = Production::factory()->create([
            'product_id' => $product->id,
            'quantity' => 15,
        ]);

        $response = $this->delete("/productions/{$production->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('productions', ['id' => $production->id]);
        $this->assertEquals(5, $product->refresh()->stock_current);
    }
}
