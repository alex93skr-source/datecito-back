<?php

namespace App\Support;

/**
 * Utilidades de ubicación. Datecito sólo trabaja con coordenadas aproximadas.
 */
final class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Redondea una coordenada para no guardar la ubicación exacta.
     */
    public static function approximate(float $value, int $decimals): float
    {
        return round($value, $decimals);
    }

    public static function distanceKm(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $dLat = deg2rad($latB - $latA);
        $dLng = deg2rad($lngB - $lngA);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Distancia redondeada hacia arriba, mínimo 1 km: nunca revela proximidad exacta.
     */
    public static function displayKm(?float $latA, ?float $lngA, ?float $latB, ?float $lngB): ?int
    {
        if ($latA === null || $lngA === null || $latB === null || $lngB === null) {
            return null;
        }

        return max(1, (int) ceil(self::distanceKm($latA, $lngA, $latB, $lngB)));
    }
}
