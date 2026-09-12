<?php

declare(strict_types=1);

namespace App\Support;

final class Like
{
    private const ESCAPE = '\\';

    /**
     * Escapa comodines LIKE (% / _) y el propio carácter de escape para que la
     * búsqueda trate el texto del usuario como literal.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $value
        );
    }

    /**
     * Aplica búsqueda LIKE escapada sobre una columna (nombre fijo, no
     * interpolado desde el usuario).
     */
    public static function apply($query, string $column, string $value)
    {
        return $query->whereRaw(
            "{$column} LIKE ? ESCAPE '".self::ESCAPE."'",
            ['%'.self::escape($value).'%']
        );
    }
}
