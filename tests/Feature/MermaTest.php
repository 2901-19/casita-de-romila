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
        $response->assertSee('No hay mermas registradas');
    }

    public function test_stores_merma(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 20]);

        $response = $this->post('/mermas', [
            'product_id' => $product->id,
            'quantity' => 3,
            'reason' => 'vencido',
            'notes' => 'Producto caducado',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('mermas', [
            'product_id' => $product->id,
            'quantity' => 3,
            'reason' => 'vencido',
            'user_id' => $user->id,
        ]);
        $product->refresh();
        $this->assertEquals(17, $product->stock_current);
    }

    public function test_validates_quantity_min(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create();

        $response = $this->post('/mermas', [
            'product_id' => $product->id,
            'quantity' => 0,
            'reason' => 'danado',
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_shows_merma_list(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['name' => 'Refresco']);
        Merma::factory()->create(['product_id' => $product->id, 'quantity' => 5, 'reason' => 'vencido']);

        $response = $this->get('/mermas');

        $response->assertStatus(200);
        $response->assertSee('Refresco');
        $response->assertSee('-5');
        $response->assertSee('Vencido');
    }

    public function test_rejects_merma_exceeding_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 3]);

        $response = $this->post('/mermas', [
            'product_id' => $product->id,
            'quantity' => 5,
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
            'reason' => 'danado',
        ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('mermas', 0);
    }
}
