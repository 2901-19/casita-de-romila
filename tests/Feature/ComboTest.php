<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/combos');

        $response->assertStatus(200);
        $response->assertViewIs('combos.index');
    }

    public function test_create_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/combos/create');

        $response->assertStatus(200);
        $response->assertViewIs('combos.create');
    }

    public function test_stores_combo(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $response = $this->post('/combos', [
            'name' => 'Combo Desayuno',
            'sale_price' => 8.50,
            'is_active' => true,
            'products' => [
                ['id' => $product1->id, 'quantity' => 1],
                ['id' => $product2->id, 'quantity' => 2],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('combos', ['name' => 'Combo Desayuno', 'sale_price' => 8.50]);
        $this->assertDatabaseHas('combo_product', [
            'combo_id' => Combo::where('name', 'Combo Desayuno')->first()->id,
            'product_id' => $product1->id,
            'quantity' => 1,
        ]);
    }

    public function test_validates_name_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/combos', [
            'name' => '',
            'sale_price' => 8.50,
            'products' => [],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_validates_sale_price_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/combos', [
            'name' => 'Combo Test',
            'sale_price' => '',
            'products' => [],
        ]);

        $response->assertSessionHasErrors('sale_price');
    }

    public function test_validates_products_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/combos', [
            'name' => 'Combo Test',
            'sale_price' => 5.00,
            'products' => [],
        ]);

        $response->assertSessionHasErrors('products');
    }

    public function test_edit_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $combo = Combo::factory()->create();

        $response = $this->get("/combos/{$combo->id}/edit");

        $response->assertStatus(200);
        $response->assertViewIs('combos.edit');
    }

    public function test_updates_combo(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $combo = Combo::factory()->create();
        $product = Product::factory()->create();

        $response = $this->put("/combos/{$combo->id}", [
            'name' => 'Combo Actualizado',
            'sale_price' => 12.00,
            'is_active' => false,
            'products' => [
                ['id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('combos', ['id' => $combo->id, 'name' => 'Combo Actualizado', 'sale_price' => 12.00, 'is_active' => false]);
    }

    public function test_toggles_active_status(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $combo = Combo::factory()->create(['is_active' => true]);

        $response = $this->patch("/combos/{$combo->id}/toggle-active");

        $response->assertRedirect();
        $this->assertDatabaseHas('combos', ['id' => $combo->id, 'is_active' => false]);
    }

    public function test_deletes_combo(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $combo = Combo::factory()->create();

        $response = $this->delete("/combos/{$combo->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('combos', ['id' => $combo->id]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->get('/combos');
        $response->assertRedirect('/login');
    }
}
