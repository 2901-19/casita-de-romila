<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/exchange-rates');

        $response->assertStatus(200);
        $response->assertViewIs('exchange-rates.index');
    }

    public function test_shows_empty_state_with_no_rates(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/exchange-rates');

        $response->assertStatus(200);
        $response->assertSee('No hay registro de tasas');
    }

    public function test_stores_new_rate(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/exchange-rates', [
            'rate' => 35.50,
            'source' => 'bcv',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('exchange_rates', [
            'rate' => 35.50,
            'source' => 'bcv',
            'user_id' => $user->id,
        ]);
    }

    public function test_validates_rate_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/exchange-rates', [
            'rate' => '',
            'source' => 'bcv',
        ]);

        $response->assertSessionHasErrors('rate');
    }

    public function test_validates_rate_positive(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/exchange-rates', [
            'rate' => 0,
            'source' => 'bcv',
        ]);

        $response->assertSessionHasErrors('rate');
    }

    public function test_validates_source_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/exchange-rates', [
            'rate' => 35.00,
            'source' => '',
        ]);

        $response->assertSessionHasErrors('source');
    }

    public function test_validates_source_invalid(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/exchange-rates', [
            'rate' => 35.00,
            'source' => 'invalid_source',
        ]);

        $response->assertSessionHasErrors('source');
    }

    public function test_shows_latest_rate_as_vigente(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        ExchangeRate::factory()->create(['rate' => 30.00, 'source' => 'bcv', 'user_id' => $user->id, 'created_at' => now()->subDay()]);
        ExchangeRate::factory()->create(['rate' => 35.00, 'source' => 'bcv', 'user_id' => $user->id, 'created_at' => now()]);

        $response = $this->get('/exchange-rates');

        $response->assertStatus(200);
        $response->assertSee('35,00');
        $response->assertSee('Vigente');
    }
}
