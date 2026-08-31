<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 150);
            $table->string('module', 50);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();
        foreach (Permission::CATALOG as $data) {
            DB::table('permissions')->insert($data + ['created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('roles')->insert([
            ['name' => 'Gerente', 'slug' => 'gerente', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Recepcionista', 'slug' => 'recepcionista', 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $gerenteId = DB::table('roles')->where('slug', 'gerente')->value('id');
        DB::table('permission_role')->insert(
            DB::table('permissions')->pluck('id')
                ->map(fn ($permissionId) => [
                    'permission_id' => $permissionId,
                    'role_id' => $gerenteId,
                ])
                ->all()
        );

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained();
        });

        $recepcionistaId = DB::table('roles')->where('slug', 'recepcionista')->value('id');
        DB::table('users')->whereNull('role_id')->update(['role_id' => $recepcionistaId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
