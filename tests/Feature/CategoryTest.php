<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/categories');

        $response->assertStatus(200);
        $response->assertViewIs('categories.index');
    }

    public function test_stores_category(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/categories', [
            'name' => 'Bebidas',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('categories', ['name' => 'Bebidas']);
    }

    public function test_validates_name_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/categories', ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_validates_name_unique(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        Category::factory()->create(['name' => 'Bebidas']);

        $response = $this->post('/categories', ['name' => 'Bebidas']);

        $response->assertSessionHasErrors('name');
    }

    public function test_updates_category(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $category = Category::factory()->create(['name' => 'Bebidas']);

        $response = $this->put("/categories/{$category->id}", ['name' => 'Bebidas Actualizadas']);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('categories', ['name' => 'Bebidas Actualizadas']);
    }

    public function test_deletes_category(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $category = Category::factory()->create(['name' => 'Bebidas']);

        $response = $this->delete("/categories/{$category->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
