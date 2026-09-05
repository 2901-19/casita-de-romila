<?php

namespace App\Support;

class Pricing
{
    /**
     * Convierte un precio en USD a Bs aplicando tasa actual y, opcionalmente,
     * redondeo hacia arriba al múltiplo de $roundStep (5, 10, 15, 20...).
     */
    public static function bs(float $usd, float $rate, ?int $roundStep = null): float
    {
        $bs = $usd * $rate;

        if ($roundStep && $roundStep > 0) {
            $bs = ceil($bs / $roundStep) * $roundStep;
        }

        return round($bs, 2);
    }
}