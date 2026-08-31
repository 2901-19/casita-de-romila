<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    public const CATALOG = [
        ['key' => 'manage-users', 'label' => 'Gestionar usuarios y roles', 'module' => 'Administración'],
        ['key' => 'manage-products', 'label' => 'Gestionar productos, categorías y combos', 'module' => 'Productos'],
        ['key' => 'manage-inventory', 'label' => 'Gestionar inventario, producción y ajustes', 'module' => 'Inventario'],
        ['key' => 'manage-waste', 'label' => 'Registrar mermas y pérdidas', 'module' => 'Inventario'],
        ['key' => 'void-sales', 'label' => 'Anular ventas registradas', 'module' => 'Ventas'],
        ['key' => 'manage-credits', 'label' => 'Gestionar clientes y créditos', 'module' => 'Ventas'],
        ['key' => 'manage-exchange-rates', 'label' => 'Gestionar tasa de cambio', 'module' => 'Finanzas'],
        ['key' => 'view-reports', 'label' => 'Consultar reportes', 'module' => 'Finanzas'],
    ];

    public const DESCRIPTIONS = [
        'manage-users' => 'Crear usuarios, definir roles y asignar permisos',
        'manage-products' => 'Administrar catálogo: productos, categorías y combos',
        'manage-inventory' => 'Ajustar stock, registrar producciones y movimientos',
        'manage-waste' => 'Reportar pérdidas o desperdicio de productos',
        'void-sales' => 'Anular ventas ya registradas',
        'manage-credits' => 'Clientes, abonos y gestión de cobros',
        'manage-exchange-rates' => 'Actualizar la tasa USD/Bs del día',
        'view-reports' => 'Consultar reportes de ventas, productos y créditos',
    ];

    protected $fillable = [
        'key',
        'label',
        'module',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
