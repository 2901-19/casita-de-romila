<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/users');

        $response->assertStatus(200);
        $response->assertViewIs('users.index');
    }

    public function test_create_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/users/create');

        $response->assertStatus(200);
        $response->assertViewIs('users.create');
    }

    public function test_stores_user(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/users', [
            'name' => 'Nuevo Usuario',
            'username' => 'nuevo.usuario',
            'password' => 'password123',
            'role_id' => Role::where('slug', 'recepcionista')->value('id'),
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['name' => 'Nuevo Usuario', 'username' => 'nuevo.usuario']);
    }

    public function test_updates_user(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $target = User::factory()->create(['name' => 'Viejo']);

        $response = $this->put("/users/{$target->id}", [
            'name' => 'Actualizado',
            'username' => $target->username,
            'role_id' => Role::where('slug', 'gerente')->value('id'),
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['name' => 'Actualizado']);
    }

    public function test_toggles_active_status(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->patch("/users/{$target->id}/toggle-active");

        $response->assertStatus(302);
        $target->refresh();
        $this->assertFalse($target->is_active);
    }

    public function test_deletes_user(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $target = User::factory()->create();

        $response = $this->delete("/users/{$target->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}
