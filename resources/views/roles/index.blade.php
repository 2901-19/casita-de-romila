@extends('layouts.app')

@section('title', 'Roles')

@section('topbar-actions')
<a href="{{ route('users.index') }}" class="btn btn-outline-brand">
    <i class="bi bi-arrow-left me-1"></i> Volver
</a>
<a href="{{ route('roles.create') }}" class="btn btn-brand">
    <i class="bi bi-plus-lg me-1"></i> Nuevo Rol
</a>
@endsection

@section('content')
<div class="card">
    <div class="p-3 border-bottom">
        <h2 class="section-title mb-0">Roles y permisos</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th scope="col">Rol</th>
                    <th scope="col" class="text-end">Permisos</th>
                    <th scope="col" class="text-end">Usuarios</th>
                    <th scope="col" class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $role->name }}</span>
                        @if($role->is_system)
                            <span class="badge-soft muted ms-1">Sistema</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <span class="count-pill">
                            <i class="bi bi-key"></i>
                            {{ $role->permissions_count }} permiso{{ $role->permissions_count !== 1 ? 's' : '' }}
                        </span>
                    </td>
                    <td class="text-end">
                        <span class="count-pill">
                            <i class="bi bi-people"></i>
                            {{ $role->users_count }} usuario{{ $role->users_count !== 1 ? 's' : '' }}
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="{{ route('roles.edit', $role) }}"
                               class="btn-icon-sm"
                               aria-label="Editar rol {{ $role->name }}"
                               title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if(! $role->is_system && $role->users_count === 0)
                                <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline" data-confirm-title="¿Eliminar el rol {{ $role->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-icon-sm warn"
                                            aria-label="Eliminar rol {{ $role->name }}"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No hay roles registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
