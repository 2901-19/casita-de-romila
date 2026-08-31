<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/roles');

        $response->assertStatus(200);
        $response->assertViewIs('roles.index');
        $response->assertSee('Gerente');
        $response->assertSee('Recepcionista');
    }

    public function test_create_loads_with_permission_checklist(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/roles/create');

        $response->assertStatus(200);
        $response->assertViewIs('roles.create');
        $response->assertSee('permissions[]');
        $response->assertSee('Consultar reportes');
    }

    public function test_stores_role_with_selected_permissions(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $permIds = Permission::whereIn('key', ['view-reports', 'void-sales'])->pluck('id');

        $response = $this->post('/roles', [
            'name' => 'Supervisor',
            'permissions' => $permIds->all(),
        ]);

        $response->assertSessionHas('success');
        $role = Role::where('name', 'Supervisor')->first();
        $this->assertNotNull($role);
        $this->assertSame('supervisor', $role->slug);
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing($permIds->all(), $role->permissions->pluck('id')->all());
    }

    public function test_stores_role_without_permissions(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/roles', ['name' => 'Sin Permisos']);

        $response->assertSessionHas('success');
        $role = Role::where('name', 'Sin Permisos')->first();
        $this->assertNotNull($role);
        $this->assertCount(0, $role->permissions);
    }

    public function test_validates_role_name_required_and_unique(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $this->post('/roles', ['name' => ''])->assertSessionHasErrors('name');

        Role::where('slug', 'recepcionista')->first();
        $this->post('/roles', ['name' => 'Recepcionista'])->assertSessionHasErrors('name');
    }

    public function test_updates_role_permissions(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $role = Role::where('slug', 'recepcionista')->first();
        $permIds = Permission::whereIn('key', ['view-reports', 'manage-waste'])->pluck('id');

        $response = $this->put("/roles/{$role->id}", [
            'name' => 'Recepcionista Senior',
            'permissions' => $permIds->all(),
        ]);

        $response->assertSessionHas('success');
        $role->refresh();
        $this->assertSame('Recepcionista Senior', $role->name);
        $this->assertEqualsCanonicalizing($permIds->all(), $role->permissions->pluck('id')->all());
    }

    public function test_system_role_always_keeps_all_permissions(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $gerente = Role::where('slug', 'gerente')->first();
        $onePerm = Permission::where('key', 'view-reports')->pluck('id')->all();

        $this->put("/roles/{$gerente->id}", [
            'name' => 'Gerente',
            'permissions' => $onePerm,
        ])->assertSessionHas('success');

        $gerente->refresh();
        $this->assertCount(Permission::count(), $gerente->permissions);
    }

    public function test_cannot_delete_system_role(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $gerente = Role::where('slug', 'gerente')->first();

        $response = $this->delete("/roles/{$gerente->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $gerente->id]);
    }

    public function test_cannot_delete_role_with_users(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $role = Role::where('slug', 'recepcionista')->first();
        User::factory()->recepcionista()->create();

        $response = $this->delete("/roles/{$role->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_deletes_role_without_users(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $role = Role::create(['name' => 'Temporal', 'slug' => 'temporal', 'is_system' => false]);

        $response = $this->delete("/roles/{$role->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_edit_form_keeps_submitted_permissions_after_validation_error(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $role = Role::create(['name' => 'Cajero', 'slug' => 'cajero', 'is_system' => false]);
        $savedPerm = Permission::where('key', 'manage-users')->first();
        $role->permissions()->sync([$savedPerm->id]);

        $permIds = Permission::whereIn('key', ['view-reports', 'manage-waste'])->pluck('id')->all();

        $this->put("/roles/{$role->id}", [
            'name' => 'Recepcionista',
            'permissions' => array_map('strval', $permIds),
        ])->assertSessionHasErrors('name');

        $response = $this->get("/roles/{$role->id}/edit");
        $response->assertStatus(200);

        $html = $response->getContent();

        foreach ($permIds as $permId) {
            $this->assertMatchesRegularExpression('/id="perm'.$permId.'"[^>]*checked/', $html);
        }

        $this->assertDoesNotMatchRegularExpression('/id="perm'.$savedPerm->id.'"[^>]*checked/', $html);
    }

    public function test_recepcionista_cannot_access_roles(): void
    {
        $user = User::factory()->recepcionista()->create();
        $this->actingAs($user);

        $this->get('/roles')->assertStatus(403);
        $this->get('/roles/create')->assertStatus(403);
        $this->post('/roles', ['name' => 'X'])->assertStatus(403);
    }
}
