@php
    $grouped = $permissions->groupBy('module');
    $moduleIcons = [
        'Administración' => 'bi-gear',
        'Productos'      => 'bi-box-seam',
        'Inventario'     => 'bi-clipboard2-data',
        'Ventas'         => 'bi-cash-stack',
        'Finanzas'       => 'bi-graph-up-arrow',
    ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <label class="form-label mb-0 fw-semibold">Permisos</label>
    @unless($disabled)
    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="toggleAllPerms">Marcar todos</button>
    @endunless
</div>

<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead>
            <tr>
                <th scope="col" class="text-center" style="width: 56px;">Marcar</th>
                <th scope="col">Permiso</th>
                <th scope="col">Descripción</th>
            </tr>
        </thead>
        <tbody>
            @foreach($grouped as $moduleName => $perms)
                <tr class="perm-module-row">
                    <td class="text-center">
                        @unless($disabled)
                        <input class="form-check-input perm-module-check m-0" type="checkbox"
                               data-module="{{ $moduleName }}"
                               aria-label="Marcar todos en {{ $moduleName }}">
                        @endunless
                    </td>
                    <td colspan="2" class="perm-module-header">
                        <i class="bi {{ $moduleIcons[$moduleName] ?? 'bi-folder' }} me-1"></i>
                        <strong>{{ $moduleName }}</strong>
                        <span class="text-muted fw-normal ms-1" style="font-size:0.78rem;">
                            ({{ $perms->count() }} permiso{{ $perms->count() !== 1 ? 's' : '' }})
                        </span>
                    </td>
                </tr>
                @foreach($perms as $perm)
                <tr class="perm-row" data-module="{{ $moduleName }}">
                    <td class="text-center">
                        <input class="form-check-input perm-check m-0" type="checkbox" name="permissions[]"
                               value="{{ $perm->id }}" id="perm{{ $perm->id }}"
                               data-module="{{ $moduleName }}"
                               aria-label="{{ $perm->label }}"
                               @checked(in_array($perm->id, $selected)) @disabled($disabled)>
                    </td>
                    <td>
                        <label for="perm{{ $perm->id }}" class="mb-0">{{ $perm->label }}</label>
                    </td>
                    <td class="text-muted">{{ \App\Models\Permission::DESCRIPTIONS[$perm->key] ?? '' }}</td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>

@if($disabled)
<div class="info-box mb-3">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <span>Este es un rol de sistema: siempre conserva todos los permisos.</span>
</div>
@endif

@push('scripts')
<script>
window.addEventListener('pageshow', function (event) {
    var nav = performance.getEntriesByType('navigation')[0];
    if (event.persisted || (nav && nav.type === 'back_forward')) {
        window.location.reload();
    }
});
@unless($disabled)
document.getElementById('toggleAllPerms').addEventListener('click', function () {
    var checks = Array.prototype.slice.call(document.querySelectorAll('.perm-check'));
    var allChecked = checks.every(function (c) { return c.checked; });
    checks.forEach(function (c) { c.checked = !allChecked; });
    this.textContent = allChecked ? 'Marcar todos' : 'Desmarcar todos';

    document.querySelectorAll('.perm-module-check').forEach(function (mc) {
        mc.checked = !allChecked;
        mc.indeterminate = false;
    });
});

document.querySelectorAll('.perm-module-check').forEach(function (modCheck) {
    modCheck.addEventListener('change', function () {
        var module = this.dataset.module;
        document.querySelectorAll('.perm-check[data-module="' + module + '"]')
            .forEach(function (c) { c.checked = modCheck.checked; });
        modCheck.indeterminate = false;
    });
});

document.querySelectorAll('.perm-check').forEach(function (permCheck) {
    permCheck.addEventListener('change', function () {
        var module = this.dataset.module;
        var allInModule = Array.prototype.slice.call(
            document.querySelectorAll('.perm-check[data-module="' + module + '"]')
        );
        var moduleCheck = document.querySelector('.perm-module-check[data-module="' + module + '"]');
        if (moduleCheck) {
            moduleCheck.checked = allInModule.every(function (c) { return c.checked; });
            moduleCheck.indeterminate = !moduleCheck.checked && allInModule.some(function (c) { return c.checked; });
        }
    });
});
@endunless
</script>
@endpush
