@extends('layouts.app')

@section('title', 'Usuarios')

@section('topbar-actions')
@can('manage-users')
<a href="{{ route('roles.index') }}" class="btn btn-outline-brand">
    <i class="bi bi-shield-lock me-1"></i> Roles
</a>
<button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#userModal">
    <i class="bi bi-person-plus me-1"></i> Nuevo Usuario
</button>
@endcan
@endsection

@section('content')
<div class="card">
    <div class="p-3 border-bottom" style="border-color: var(--border) !important;">
        <h2 class="section-title mb-0">Gestión de usuarios</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="user-avatar" aria-hidden="true">{{ $user->name[0] }}</span>
                            <span class="user-username">{{ $user->username }}</span>
                        </div>
                    </td>
                    <td class="fw-semibold">{{ $user->name }}</td>
                    <td>
                        <span class="badge badge-role {{ $user->role?->slug }}-role rounded-pill">
                            {{ $user->role?->name ?? '—' }}
                        </span>
                    </td>
                    <td>
                        @if($user->is_active)
                            <span class="status activo">
                                <span class="status-dot" aria-hidden="true"></span>Activo
                            </span>
                        @else
                            <span class="status inactivo">
                                <span class="status-dot" aria-hidden="true"></span>Inactivo
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($user->last_login_at)
                            <span class="text-muted" title="{{ $user->last_login_at->format('d/m/Y H:i') }}">
                                {{ $user->last_login_at->locale('es')->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-muted">Nunca</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            @can('manage-users')
                            <a href="{{ route('users.edit', $user) }}"
                               class="btn-icon-sm"
                               aria-label="Editar usuario {{ $user->name }}">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <form action="{{ route('users.toggle-active', $user) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                @if($user->is_active)
                                    <button type="submit"
                                            class="btn-icon-sm warn"
                                            aria-label="Desactivar usuario {{ $user->name }}"
                                            title="Desactivar">
                                        <i class="bi bi-person-dash"></i>
                                    </button>
                                @else
                                    <button type="submit"
                                            class="btn-icon-sm act"
                                            aria-label="Activar usuario {{ $user->name }}"
                                            title="Activar">
                                        <i class="bi bi-person-check"></i>
                                    </button>
                                @endif
                            </form>

                            @if($user->id !== auth()->id())
                                <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline" data-confirm-title="¿Eliminar el usuario {{ $user->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-icon-sm warn"
                                            aria-label="Eliminar usuario {{ $user->name }}"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@section('modals')
@can('manage-users')
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre completo</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               id="name" name="name" value="{{ old('name') }}"
                               placeholder="Ej. Ana Rivas" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de usuario</label>
                        <input type="text" class="form-control @error('username') is-invalid @enderror"
                               id="username" name="username" value="{{ old('username') }}"
                               placeholder="nombre.usuario" required minlength="3" maxlength="30">
                        <div class="form-text">Minúsculas, números, punto, guion y guion bajo.</div>
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Rol</label>
                        <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
                            <option value="">Seleccionar rol</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               id="password" name="password"
                               placeholder="••••••••" required minlength="6">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control"
                               id="password_confirmation" name="password_confirmation"
                               placeholder="••••••••" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand">
                        <i class="bi bi-check2-circle me-1"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
