<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permission::CATALOG as $data) {
            Permission::firstOrCreate(['key' => $data['key']], $data);
        }

        $gerente = Role::firstOrCreate(
            ['slug' => 'gerente'],
            ['name' => 'Gerente', 'is_system' => true]
        );
        $gerente->permissions()->sync(Permission::pluck('id'));

        Role::firstOrCreate(
            ['slug' => 'recepcionista'],
            ['name' => 'Recepcionista', 'is_system' => false]
        );
    }
}
