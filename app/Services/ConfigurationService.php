<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConfigurationService
{
    /**
     * Tiempo de caché para configuraciones (en segundos)
     */
    protected const CACHE_TTL = 3600; // 1 hora

    /**
     * Obtiene un valor de configuración
     *
     * @param  string  $key  Clave de configuración
     * @param  mixed  $default  Valor por defecto si no se encuentra la configuración
     * @return mixed Valor de configuración
     */
    public function getConfig(string $key, $default = null)
    {
        // Intentar obtener de caché primero
        return Cache::remember('config.'.$key, self::CACHE_TTL, function () use ($key, $default) {
            $config = DB::table('configurations')->where('key', $key)->first();
            if ($config && $config->value !== null) {
                // Decodificar el valor si es un JSON
                if ($this->isJson($config->value)) {
                    return json_decode($config->value, true);
                }

                // Si el valor es un booleano como string, convertirlo
                if ($config->value === 'true') {
                    return true;
                }
                if ($config->value === 'false') {
                    return false;
                }

                // Si es un número, convertirlo
                if (is_numeric($config->value)) {
                    // Si tiene punto decimal, es float
                    if (strpos($config->value, '.') !== false) {
                        return (float) $config->value;
                    }

                    // Si no, es integer
                    return (int) $config->value;
                }

                return $config->value;
            }

            return $default;
        });
    }

    /**
     * Establece un valor de configuración
     *
     * @param  string  $key  Clave de configuración
     * @param  mixed  $value  Valor de configuración
     * @return bool Resultado de la operación
     */
    public function setConfig(string $key, $value): bool
    {
        \Log::info("🔧 ConfigurationService::setConfig - Key: {$key}, Value: " . json_encode($value) . " (tipo: " . gettype($value) . ")");
        
        // Si el valor es un array o un objeto, convertirlo a JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        // Si el valor es booleano, convertirlo a string
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        \Log::info("🔧 ConfigurationService::setConfig - Valor final a guardar: " . json_encode($value));

        try {
            DB::table('configurations')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_at' => now(),
                ]
            );

            // Limpiar caché para esta clave
            Cache::forget('config.'.$key);

            return true;
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error al guardar configuración: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Elimina una configuración
     *
     * @param  string  $key  Clave de configuración
     * @return bool Resultado de la operación
     */
    public function deleteConfig(string $key): bool
    {
        try {
            DB::table('configurations')->where('key', $key)->delete();

            // Limpiar caché para esta clave
            Cache::forget('config.'.$key);

            return true;
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error al eliminar configuración: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Verifica si un string es un JSON válido
     *
     * @param  string  $string  String a verificar
     * @return bool Es JSON válido
     */
    private function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
