<?php

declare(strict_types=1);

namespace App\Support;

final class Dates
{
    /**
     * True si el valor es una fecha parseable y realista (Y-m-d o similar).
     */
    public static function valid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return false;
        }

        return checkdate((int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts));
    }
}
