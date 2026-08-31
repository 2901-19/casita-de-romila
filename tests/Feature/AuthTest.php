<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_remember_me_reauthenticates_after_session_is_lost(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password',
            'remember' => '1',
        ]);

        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));
        $this->assertNotNull($rememberCookie);

        $user->forceFill(['last_login_at' => now()->subHours(2)])->saveQuietly();

        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();

        $dashboard = $this->withUnencryptedCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get('/dashboard');

        $dashboard->assertOk();
        $this->assertAuthenticated();
        $this->assertTrue($this->app['auth']->guard()->viaRemember());
        $this->assertTrue($user->fresh()->last_login_at->gt(now()->subMinute()));
    }

    public function test_without_remember_me_lost_session_requires_relogin(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);
        $this->assertNull(
            collect($response->headers->getCookies())
                ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'))
        );

        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->assertNull($user->last_login_at);

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertFalse(session()->has('status'));
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
