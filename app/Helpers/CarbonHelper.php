<?php

namespace App\Helpers;

use Carbon\Carbon;

class CarbonHelper
{
    /**
     * Convierte un valor a un entero seguro para Carbon
     *
     * @param  mixed  $value
     * @param  int  $default  Valor predeterminado si la conversión falla
     */
    public static function safeIntValue($value, int $default = 0): int
    {
        // Si ya es un entero, devolverlo
        if (is_int($value)) {
            return $value;
        }

        // Si es un número, convertirlo a entero
        if (is_numeric($value)) {
            return intval($value);
        }

        // Si es una cadena que representa un número
        if (is_string($value) && is_numeric($value)) {
            return intval($value);
        }

        // Si es un objeto Carbon, obtener su timestamp
        if ($value instanceof Carbon) {
            return $value->timestamp;
        }

        // En cualquier otro caso, devolver el valor predeterminado
        return $default;
    }

    /**
     * Calcula una fecha segura basada en unidades
     *
     * @param  Carbon  $date  Fecha base
     * @param  string  $unit  Unidad de tiempo (minute, hour, day, etc.)
     * @param  mixed  $amount  Cantidad de unidades
     */
    public static function safeAddUnit(Carbon $date, string $unit, $amount): Carbon
    {
        // Convertir amount a un valor seguro
        $safeAmount = self::safeIntValue($amount);

        // Usar método de adición seguro
        return $date->add($unit, $safeAmount);
    }
}
