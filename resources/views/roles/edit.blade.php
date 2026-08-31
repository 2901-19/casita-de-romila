@extends('layouts.app')

@section('title', 'Editar Rol')

@section('topbar-actions')
<a href="{{ route('roles.index') }}" class="btn btn-outline-brand">
    <i class="bi bi-arrow-left me-1"></i> Volver
</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="modal-title mb-0">Editar Rol: {{ $role->name }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('roles.update', $role) }}" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name" class="form-label">Nombre del rol</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror"
                       id="name" name="name" value="{{ old('name', $role->name) }}"
                       required maxlength="50">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            @include('roles._permissions', [
                'permissions' => $permissions,
                'selected' => collect(old('permissions', $role->permissions->pluck('id')->all()))
                    ->map(fn ($v) => (int) $v)->all(),
                'disabled' => $role->is_system,
            ])

            <div class="d-flex gap-2 mt-3">
                <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary flex-grow-1">Cancelar</a>
                <button type="submit" class="btn btn-brand flex-grow-1">
                    <i class="bi bi-check2-circle me-1"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
