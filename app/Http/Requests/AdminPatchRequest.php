<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Request específico para operaciones PATCH de administrador
 * Maneja toggles y actualizaciones parciales
 *
 * @method array all(array|mixed|null $keys = null) Obtiene todos los datos de entrada
 * @method void merge(array $input) Fusiona datos en la entrada de la solicitud
 * @method mixed input(string $key = null, mixed $default = null) Obtiene un valor de entrada
 * @method bool has(string|array $key) Determina si la solicitud contiene una clave de entrada dada
 * @method string|null ip() Obtiene la dirección IP del cliente
 * @method \Illuminate\Http\UploadedFile|array|null file(string $key = null, mixed $default = null) Obtiene un archivo de la solicitud
 * @method bool hasFile(string $key) Determina si el archivo subido está presente en la solicitud
 * @method string method() Obtiene el método de la solicitud
 * @method bool isMethod(string $method) Determina si el método de la solicitud coincide con el dado
 * @method string|null header(string $key = null, string|array|null $default = null) Obtiene un valor de encabezado de la solicitud
 * @method string url() Obtiene la URL completa de la solicitud
 * @method string fullUrl() Obtiene la URL completa de la solicitud con parámetros de consulta
 * @method string|null route(string $param = null, mixed $default = null) Obtiene un parámetro de ruta
 * @method \App\Models\User|null user() Obtiene el usuario autenticado
 */
class AdminPatchRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->isAdmin();
    }

    /**
     * Reglas de validación para operaciones PATCH.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Para productos
            'featured' => 'sometimes|boolean',
            'published' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:active,inactive,draft',
            'stock' => 'sometimes|integer|min:0',
            'price' => 'sometimes|numeric|min:0',
            'name' => 'sometimes|string|max:255',
            'discount_percentage' => 'sometimes|numeric|min:0|max:100',

            // Para categorías
            'is_active' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
            'parent_id' => 'sometimes|nullable|integer|exists:categories,id',
            'slug' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'featured.boolean' => 'El campo destacado debe ser verdadero o falso',
            'published.boolean' => 'El campo publicado debe ser verdadero o falso',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso',
            'status.in' => 'El estado debe ser: active, inactive o draft',
            'stock.integer' => 'El stock debe ser un número entero',
            'stock.min' => 'El stock debe ser mayor o igual a cero',
            'price.numeric' => 'El precio debe ser un número',
            'price.min' => 'El precio debe ser mayor o igual a cero',
            'discount_percentage.numeric' => 'El descuento debe ser un número',
            'discount_percentage.min' => 'El descuento debe ser mayor o igual a cero',
            'discount_percentage.max' => 'El descuento no puede ser mayor al 100%',
            'order.integer' => 'El orden debe ser un número entero',
            'order.min' => 'El orden debe ser mayor o igual a cero',
            'parent_id.exists' => 'La categoría padre seleccionada no existe',
        ];
    }

    /**
     * Prepara los datos para la validación - OPTIMIZADO PARA PATCH JSON.
     */
    protected function prepareForValidation(): void
    {
        $requestData = $this->all();
        $mergeData = [];

        // ✅ AJUSTE MÍNIMO: Mejorar conversión de booleanos
        foreach (['featured', 'published', 'is_active'] as $field) {
            if (array_key_exists($field, $requestData)) {
                $value = $requestData[$field];

                // ✅ MEJORADO: Conversión más robusta a booleano
                if (is_string($value)) {
                    $mergeData[$field] = in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
                } elseif (is_numeric($value)) {
                    $mergeData[$field] = (bool) $value;
                } else {
                    $mergeData[$field] = (bool) $value;
                }

                // ✅ AGREGADO: Log para debugging en desarrollo
                if (config('app.debug')) {
                    Log::info("AdminPatchRequest: Procesando {$field}", [
                        'original' => $value,
                        'original_type' => gettype($value),
                        'converted' => $mergeData[$field],
                        'converted_type' => gettype($mergeData[$field]),
                    ]);
                }
            }
        }

        // ✅ Procesar números (mantenido igual)
        foreach (['stock', 'order', 'parent_id'] as $field) {
            if (array_key_exists($field, $requestData)) {
                $value = $requestData[$field];

                if ($field === 'parent_id' && ($value === null || $value === '')) {
                    $mergeData[$field] = null;
                } elseif (is_numeric($value)) {
                    $mergeData[$field] = (int) $value;
                }
            }
        }

        // ✅ Procesar decimales (mantenido igual)
        foreach (['price', 'discount_percentage'] as $field) {
            if (array_key_exists($field, $requestData)) {
                $value = $requestData[$field];

                if (is_numeric($value)) {
                    $mergeData[$field] = (float) $value;
                }
            }
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);

            // ✅ AGREGADO: Log final solo en desarrollo
            if (config('app.debug')) {
                Log::info('AdminPatchRequest: Datos finales procesados', $mergeData);
            }
        }
    }

    /**
     * ✅ AGREGADO: Procesar datos después de validación exitosa
     * Asegurar tipos correctos para la base de datos
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if (config('app.debug')) {
            Log::info('AdminPatchRequest: Datos validados antes de procesamiento final:', $validated);
        }

        // ✅ IMPORTANTE: Asegurar tipos correctos después de validación
        if (isset($validated['is_active'])) {
            $validated['is_active'] = (bool) $validated['is_active'];
        }

        if (isset($validated['featured'])) {
            $validated['featured'] = (bool) $validated['featured'];
        }

        if (isset($validated['published'])) {
            $validated['published'] = (bool) $validated['published'];
        }

        if (isset($validated['order'])) {
            $validated['order'] = (int) $validated['order'];
        }

        if (isset($validated['parent_id'])) {
            $validated['parent_id'] = $validated['parent_id'] ? (int) $validated['parent_id'] : null;
        }

        if (config('app.debug')) {
            Log::info('AdminPatchRequest: Datos validados finales:', $validated);
        }

        return $key ? ($validated[$key] ?? $default) : $validated;
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verificar permisos adicionales si es necesario
            $user = Auth::user();
            if (! $user || ! $user->isAdmin()) {
                $validator->errors()->add('authorization', 'No tienes permisos de administrador');
            }
        });
    }
}
