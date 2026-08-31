<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_se_bloquea_tras_5_intentos_fallidos(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'incorrecto']);
        }

        $response = $this->post('/login', ['username' => $user->username, 'password' => 'password']);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Demasiados intentos',
            collect(session('errors')->getBag('default')->get('username'))->first()
        );
    }

    public function test_login_rechaza_usuarios_inactivos(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->post('/login', ['username' => $user->username, 'password' => 'password']);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
    }

    public function test_usuario_desactivado_en_curso_es_expulsado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_cerrar_sesion_endpoint_limitado_a_10_peticiones_por_minuto(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/lanzador/cerrar-sesion', ['token' => "t-$i"])->assertStatus(204);
        }

        $this->post('/lanzador/cerrar-sesion', ['token' => 'extra'])->assertStatus(429);
    }

    public function test_sessions_purge_elimina_sesiones_expiradas_y_lanzador_huerfano(): void
    {
        config()->set('session.driver', 'database');

        DB::table('sessions')->insert([
            ['id' => 'sesion-vieja'.str_repeat('a', 30), 'payload' => '{}', 'last_activity' => now()->getTimestamp() - 9999],
            ['id' => 'sesion-fresca'.str_repeat('b', 30), 'payload' => '{}', 'last_activity' => now()->getTimestamp()],
        ]);
        DB::table('lanzador_sesiones')->insert([
            ['token' => str_repeat('1', 64), 'session_id' => 'sesion-vieja'.str_repeat('a', 30)],
            ['token' => str_repeat('2', 64), 'session_id' => 'sesion-inexistente'],
            ['token' => str_repeat('3', 64), 'session_id' => 'sesion-fresca'.str_repeat('b', 30)],
        ]);

        $this->artisan('sessions:purge')->assertSuccessful();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-vieja'.str_repeat('a', 30)]);
        $this->assertDatabaseHas('sessions', ['id' => 'sesion-fresca'.str_repeat('b', 30)]);
        $this->assertDatabaseMissing('lanzador_sesiones', ['token' => str_repeat('1', 64)]);
        $this->assertDatabaseMissing('lanzador_sesiones', ['token' => str_repeat('2', 64)]);
        $this->assertDatabaseHas('lanzador_sesiones', ['token' => str_repeat('3', 64)]);
    }
}
