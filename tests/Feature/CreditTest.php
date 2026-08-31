<?php

namespace Tests\Feature;

use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/credits');

        $response->assertStatus(200);
        $response->assertViewIs('credits.index');
    }

    public function test_stores_customer(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/credits', [
            'name' => 'Juan Pérez',
            'phone' => '0412-1234567',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customers', ['name' => 'Juan Pérez']);
    }

    public function test_validates_name_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/credits', ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_requires_amount_when_limit_is_monto(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/credits', [
            'name' => 'Sin Monto',
            'credit_limit_type' => 'monto',
            'credit_limit_amount' => null,
        ]);

        $response->assertSessionHasErrors('credit_limit_amount');
        $this->assertDatabaseMissing('customers', ['name' => 'Sin Monto']);
    }

    public function test_update_requires_amount_when_limit_is_monto(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['credit_limit_type' => 'libre']);

        $response = $this->put("/credits/{$customer->id}", [
            'name' => $customer->name,
            'credit_limit_type' => 'monto',
            'credit_limit_amount' => null,
        ]);

        $response->assertSessionHasErrors('credit_limit_amount');
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'credit_limit_type' => 'libre',
        ]);
    }

    public function test_update_to_libre_clears_defined_amount(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create([
            'credit_limit_type' => 'monto',
            'credit_limit_amount' => 25.50,
        ]);

        $response = $this->put("/credits/{$customer->id}", [
            'name' => $customer->name,
            'credit_limit_type' => 'libre',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'credit_limit_type' => 'libre',
            'credit_limit_amount' => null,
        ]);
    }

    public function test_shows_customer_detail(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['name' => 'María López', 'balance' => -50.00]);

        $response = $this->get("/credits/{$customer->id}");

        $response->assertStatus(200);
        $response->assertViewIs('credits.show');
        $response->assertSee('María López');
        $response->assertSee('50,00');
        $response->assertSee('Adeuda');
    }

    public function test_filters_by_status_deuda(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        Customer::factory()->create(['name' => 'Con Deuda', 'balance' => -100.00]);
        Customer::factory()->create(['name' => 'Al Día', 'balance' => 0.00]);

        $response = $this->get('/credits?status=deuda');

        $response->assertStatus(200);
        $response->assertSee('Con Deuda');
        $response->assertDontSee('Al Día');
    }

    public function test_filters_by_search(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        Customer::factory()->create(['name' => 'Pedro Gómez']);
        Customer::factory()->create(['name' => 'María López']);

        $response = $this->get('/credits?search=Pedro');

        $response->assertStatus(200);
        $response->assertSee('Pedro Gómez');
        $response->assertDontSee('María López');
    }

    public function test_updates_customer_credit_limit(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create();

        $response = $this->put("/credits/{$customer->id}", [
            'name' => 'Cliente Editado',
            'phone' => '0412-9999999',
            'credit_limit_type' => 'monto',
            'credit_limit_amount' => 25.50,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'credit_limit_type' => 'monto',
            'credit_limit_amount' => 25.50,
        ]);
    }

    private function createCreditSale(Customer $customer, float $priceBs = 1000.00): Sale
    {
        ExchangeRate::factory()->create(['rate' => 100]);
        $product = Product::factory()->create(['control_type' => 'demanda', 'sale_price' => 10]);

        $this->postJson('/pos', [
            'cart' => [['product_id' => $product->id, 'name' => $product->name, 'price' => $priceBs, 'quantity' => 1]],
            'payment_method' => 'credito',
            'customer_id' => $customer->id,
        ])->assertStatus(200);

        return Sale::latest('id')->first();
    }

    public function test_pays_credit_in_full_and_settles_sale(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create();
        $sale = $this->createCreditSale($customer, 1000.00);

        $this->assertEquals('pendiente', $sale->status);

        $response = $this->post("/credits/{$customer->id}/credits/{$sale->id}/pay");

        $response->assertSessionHas('success');

        $sale->refresh();
        $this->assertEquals('completada', $sale->status);

        $customer->refresh();
        $this->assertEquals(0.00, (float) $customer->balance);

        $this->assertDatabaseHas('credit_movements', [
            'sale_id' => $sale->id,
            'type' => 'pago',
            'amount' => 10.00,
            'rate' => 100,
        ]);
    }

    public function test_cannot_pay_credit_twice(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create();
        $sale = $this->createCreditSale($customer, 1000.00);

        $this->post("/credits/{$customer->id}/credits/{$sale->id}/pay")->assertSessionHas('success');
        $this->post("/credits/{$customer->id}/credits/{$sale->id}/pay")->assertSessionHas('error');

        $this->assertEquals(1, CreditMovement::where('type', 'pago')->count());
        $customer->refresh();
        $this->assertEquals(0.00, (float) $customer->balance);
    }

    public function test_cannot_pay_another_customers_credit(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $sale = $this->createCreditSale($owner, 1000.00);

        $this->post("/credits/{$other->id}/credits/{$sale->id}/pay")->assertSessionHas('error');

        $sale->refresh();
        $this->assertEquals('pendiente', $sale->status);
    }

    public function test_paying_one_credit_keeps_others_pending(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create();
        $first = $this->createCreditSale($customer, 1000.00);
        $second = $this->createCreditSale($customer, 500.00);

        $this->post("/credits/{$customer->id}/credits/{$first->id}/pay")->assertSessionHas('success');

        $second->refresh();
        $this->assertEquals('pendiente', $second->status);

        $customer->refresh();
        $this->assertEquals(-5.00, (float) $customer->balance);
    }

    public function test_canceling_credit_sale_reverses_balance(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create();
        $sale = $this->createCreditSale($customer, 1000.00);

        $customer->refresh();
        $this->assertEquals(-10.00, (float) $customer->balance);

        $response = $this->delete("/sales/{$sale->id}", ['cancel_reason' => 'Prueba de anulación']);

        $response->assertSessionHas('success');

        $sale->refresh();
        $this->assertEquals('anulada', $sale->status);

        $this->assertDatabaseHas('credit_movements', [
            'sale_id' => $sale->id,
            'type' => 'abono',
            'amount' => 10.00,
            'rate' => 100,
        ]);

        $customer->refresh();
        $this->assertEquals(0.00, (float) $customer->balance);
    }
}
