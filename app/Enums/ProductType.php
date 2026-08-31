<?php
namespace App\Enums;
enum ProductType: string {
    case Inventariable = 'inventariable';
    case Demanda = 'demanda';
    case Produccion = 'produccion';

    public function label(): string {
        return match($this) {
            self::Inventariable => 'Inventariable',
            self::Demanda => 'Demanda',
            self::Produccion => 'Producción',
        };
    }
}
