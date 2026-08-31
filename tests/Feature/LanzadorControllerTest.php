<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanzadorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'database');
    }

    public function test_token_desconocido_responde_204_sin_afectar_sesiones(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $sesion = session()->getId();

        $this->post('/lanzador/cerrar-sesion', ['token' => 'token-inexistente'])
            ->assertStatus(204);

        $this->assertDatabaseHas('sessions', ['id' => $sesion]);
        $this->get('/pos')->assertOk();
    }

    public function test_cerrar_sesion_destruye_la_sesion_vinculada_al_token(): void
    {
        $user = User::factory()->create();

        $this->followingRedirects()->get('/pos?_lanzador=token-prueba');
        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $this->get('/pos')->assertOk();

        $sesion = session()->getId();

        $this->assertDatabaseHas('lanzador_sesiones', [
            'token' => hash('sha256', 'token-prueba'),
            'session_id' => $sesion,
            'user_id' => $user->id,
        ]);

        $this->post('/lanzador/cerrar-sesion', ['token' => 'token-prueba'])
            ->assertStatus(204);

        $this->assertDatabaseMissing('sessions', ['id' => $sesion]);
        $this->assertDatabaseMissing('lanzador_sesiones', ['token' => hash('sha256', 'token-prueba')]);
    }
}
