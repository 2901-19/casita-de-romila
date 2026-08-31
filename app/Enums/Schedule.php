<?php

namespace App\Enums;

enum Schedule: string
{
    case Manana = 'manana';
    case FindeNoche = 'finde_noche';
    case Ambos = 'ambos';

    public function label(): string
    {
        return match($this) {
            self::Manana => 'Mañana',
            self::FindeNoche => 'Finde Noche',
            self::Ambos => 'Ambos',
        };
    }
}
