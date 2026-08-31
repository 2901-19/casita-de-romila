<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function recepcionista(): User
    {
        return User::factory()->recepcionista()->create();
    }

    public function test_recepcionista_keeps_dashboard_pos_and_sales_only(): void
    {
        $this->actingAs($this->recepcionista());

        $this->get('/dashboard')->assertStatus(200);
        $this->get('/pos')->assertStatus(200);
        $this->get('/sales')->assertStatus(200);

        $this->get('/products')->assertStatus(403);
        $this->get('/categories')->assertStatus(403);
        $this->get('/inventory')->assertStatus(403);
        $this->get('/productions')->assertStatus(403);
        $this->get('/mermas')->assertStatus(403);
        $this->get('/exchange-rates')->assertStatus(403);
    }

    public function test_role_with_single_permission_only_accesses_its_module(): void
    {
        $role = Role::create(['name' => 'Inventariador', 'slug' => 'inventariador', 'is_system' => false]);
        $role->permissions()->sync(Permission::where('key', 'manage-inventory')->pluck('id'));
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        $this->get('/inventory')->assertStatus(200);
        $this->get('/productions')->assertStatus(200);

        $this->get('/products')->assertStatus(403);
        $this->get('/mermas')->assertStatus(403);
        $this->get('/exchange-rates')->assertStatus(403);
        $this->get('/reports')->assertStatus(403);
        $this->get('/users')->assertStatus(403);
    }

    public function test_menu_only_shows_links_for_granted_permissions(): void
    {
        $this->actingAs($this->recepcionista());

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee(route('pos.index'));
        $response->assertSee(route('sales.index'));
        $response->assertDontSee(route('products.index'));
        $response->assertDontSee(route('inventory.index'));
        $response->assertDontSee(route('exchange-rates.index'));
        $response->assertDontSee(route('reports.index'));
    }

    public function test_recepcionista_cannot_manage_products(): void
    {
        $this->actingAs($this->recepcionista());
        $product = Product::factory()->create();

        $this->get('/products/create')->assertStatus(403);
        $this->post('/products', ['name' => 'X'])->assertStatus(403);
        $this->get("/products/{$product->id}/edit")->assertStatus(403);
        $this->put("/products/{$product->id}", [])->assertStatus(403);
        $this->patch("/products/{$product->id}/toggle-active")->assertStatus(403);
        $this->delete("/products/{$product->id}")->assertStatus(403);
        $this->post('/categories', ['name' => 'X'])->assertStatus(403);
    }

    public function test_recepcionista_cannot_manage_inventory(): void
    {
        $this->actingAs($this->recepcionista());

        $this->post('/inventory', [])->assertStatus(403);
        $this->post('/productions', [])->assertStatus(403);
        $this->post('/mermas', [])->assertStatus(403);
    }

    public function test_recepcionista_cannot_void_sales(): void
    {
        $user = $this->recepcionista();
        $this->actingAs($user);
        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);

        $this->delete("/sales/{$sale->id}", ['cancel_reason' => 'X'])->assertStatus(403);
    }

    public function test_recepcionista_cannot_access_gated_modules(): void
    {
        $this->actingAs($this->recepcionista());

        $this->get('/reports')->assertStatus(403);
        $this->get('/reports/sales')->assertStatus(403);
        $this->get('/reports/products')->assertStatus(403);
        $this->get('/reports/credits')->assertStatus(403);
        $this->get('/credits')->assertStatus(403);
        $this->post('/credits', [])->assertStatus(403);
        $this->post('/exchange-rates', ['rate' => 100, 'source' => 'bcv'])->assertStatus(403);
        $this->get('/users')->assertStatus(403);
        $this->get('/roles')->assertStatus(403);
    }

    public function test_gerente_can_access_gated_modules(): void
    {
        $this->actingAs(User::factory()->gerente()->create());

        $this->get('/reports')->assertStatus(200);
        $this->get('/credits')->assertStatus(200);
        $this->get('/exchange-rates')->assertStatus(200);
        $this->get('/users')->assertStatus(200);
        $this->get('/roles')->assertStatus(200);
        $this->get('/products/create')->assertStatus(200);
    }
}
