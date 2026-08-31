<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])->orderBy('name')->get();
        $permissions = Permission::orderBy('module')->orderBy('label')->get();

        return view('roles.index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function create(): View
    {
        return view('roles.create', [
            'permissions' => Permission::orderBy('module')->orderBy('label')->get(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'slug' => str($data['name'])->slug()->toString(),
            'is_system' => false,
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol creado exitosamente.');
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::orderBy('module')->orderBy('label')->get(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        $role->update(['name' => $data['name']]);

        if ($role->is_system) {
            $role->permissions()->sync(Permission::pluck('id'));
        } else {
            $role->permissions()->sync($data['permissions'] ?? []);
        }

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol actualizado exitosamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()
                ->route('roles.index')
                ->with('error', 'El rol de sistema no puede eliminarse.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('roles.index')
                ->with('error', "No se puede eliminar el rol {$role->name} porque tiene usuarios asignados.");
        }

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Rol eliminado.');
    }
}
