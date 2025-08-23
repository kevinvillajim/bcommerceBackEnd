<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @method \App\Models\User|null user() Obtiene el usuario autenticado
 * @method mixed route(string $param = null, mixed $default = null) Obtiene un parámetro de ruta
 * @method string getMethod() Obtiene el método HTTP de la solicitud
 * @method bool has(string $key) Determina si la solicitud contiene una clave de entrada dada
 * @method mixed input(string $key = null, mixed $default = null) Obtiene un valor de entrada
 * @method void merge(array $input) Fusiona datos en la entrada de la solicitud
 * @method array all(array|mixed|null $keys = null) Obtiene todos los datos de entrada
 * @method bool isMethod(string $method) Determina si el método de la solicitud coincide con el dado
 * @method string|null header(string $key = null, string|array|null $default = null) Obtiene un valor de encabezado
 * @method \Illuminate\Http\UploadedFile|array|null file(string $key = null, mixed $default = null) Obtiene un archivo
 * @method bool hasFile(string $key) Determina si el archivo subido está presente
 * @method string|null ip() Obtiene la dirección IP del cliente
 * @method string url() Obtiene la URL completa de la solicitud
 * @method string fullUrl() Obtiene la URL completa con parámetros de consulta
 * @method mixed validated(string|null $key = null, mixed $default = null) Obtiene los datos validados
 */
class ProductRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        // ✅ Solución robusta: Usar Auth facade directamente
        $user = Auth::user();

        // ✅ Usar métodos existentes del modelo User
        return $user && ($user->isSeller() || $user->isAdmin());
    }

    /**
     * Reglas de validación para la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // ✅ Solución robusta: Obtener ID de múltiples fuentes
        $productId = null;
        if (method_exists($this, 'route')) {
            $productId = $this->route('id') ?: $this->route('product');
        }

        // Fallback usando request URI
        if (! $productId && preg_match('/\/products\/(\d+)/', request()->getRequestUri(), $matches)) {
            $productId = $matches[1] ?? null;
        }

        $rules = [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'depth' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:50',

            // CORREGIDO: Validaciones para arrays
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:50',
            'sizes' => 'nullable|array',
            'sizes.*' => 'string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',

            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($productId),
            ],

            // CORREGIDO: Validaciones para atributos como array
            'attributes' => 'nullable|array',
            'attributes.*.key' => 'required_with:attributes|string|max:100',
            'attributes.*.value' => 'required_with:attributes|string|max:255',

            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB

            // CORREGIDO: Validaciones para booleanos (acepta 1/0, true/false, "1"/"0")
            'featured' => 'nullable|boolean',
            'published' => 'nullable|boolean',
            'replace_images' => 'nullable|boolean',

            'status' => 'nullable|string|in:active,inactive,draft',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'short_description' => 'nullable|string|max:255',

            // Para actualización: campos para manejo de imágenes
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|min:0',
        ];

        // ✅ Solución robusta: Verificar método HTTP
        $method = request()->getMethod();
        if (in_array($method, ['PATCH', 'PUT'])) {
            $rules = array_merge($rules, [
                'name' => 'sometimes|required|string|max:255',
                'category_id' => 'sometimes|required|integer|exists:categories,id',
                'description' => 'sometimes|required|string',
                'short_description' => 'sometimes|nullable|string|max:255',
                'price' => 'sometimes|required|numeric|min:0',
                'stock' => 'sometimes|required|integer|min:0',
            ]);
        }

        return $rules;
    }

    /**
     * Mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es obligatorio',
            'category_id.required' => 'La categoría es obligatoria',
            'category_id.exists' => 'La categoría seleccionada no existe',
            'description.required' => 'La descripción del producto es obligatoria',
            'price.required' => 'El precio es obligatorio',
            'price.numeric' => 'El precio debe ser un número',
            'price.min' => 'El precio debe ser mayor o igual a cero',
            'stock.required' => 'El stock es obligatorio',
            'stock.integer' => 'El stock debe ser un número entero',
            'stock.min' => 'El stock debe ser mayor o igual a cero',
            'slug.unique' => 'Este slug ya está en uso',
            'sku.unique' => 'Este SKU ya está en uso',
            'images.*.image' => 'Los archivos deben ser imágenes',
            'images.*.mimes' => 'Las imágenes deben ser de tipo: jpeg, png, jpg, gif, webp',
            'images.*.max' => 'El tamaño máximo de cada imagen es 5MB',
            'discount_percentage.max' => 'El descuento no puede ser mayor al 100%',
            'colors.array' => 'Los colores deben ser una lista',
            'sizes.array' => 'Las tallas deben ser una lista',
            'tags.array' => 'Las etiquetas deben ser una lista',
            'attributes.array' => 'Los atributos deben ser una lista',
            'featured.boolean' => 'El campo destacado debe ser verdadero o falso',
            'published.boolean' => 'El campo publicado debe ser verdadero o falso',
        ];
    }

    /**
     * Prepara los datos para la validación - CORREGIDO COMPLETAMENTE.
     */
    protected function prepareForValidation(): void
    {
        // ✅ DETECTAR SI ES UNA SOLICITUD PATCH CON JSON
        $isPatchJson = $this->isMethod('PATCH') &&
            $this->header('Content-Type') === 'application/json';

        if ($isPatchJson) {
            // Para solicitudes PATCH JSON, los datos ya vienen procesados correctamente
            $this->preparePatchJsonValidation();
        } else {
            // Para solicitudes FormData, usar el procesamiento existente
            $this->prepareFormDataValidation();
        }
    }

    /**
     * Procesa validación para solicitudes PATCH JSON
     */
    protected function preparePatchJsonValidation(): void
    {
        $requestData = $this->all();
        $mergeData = [];

        // ✅ Para JSON, los booleanos ya vienen como tipos correctos
        foreach (['featured', 'published', 'replace_images'] as $field) {
            if (array_key_exists($field, $requestData)) {
                $value = $requestData[$field];

                // Asegurar que sea booleano
                if (is_string($value)) {
                    $mergeData[$field] = in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
                } elseif (is_numeric($value)) {
                    $mergeData[$field] = (bool) $value;
                } else {
                    $mergeData[$field] = (bool) $value;
                }
            }
        }

        // ✅ Asegurar user_id
        if (! isset($requestData['user_id'])) {
            $mergeData['user_id'] = Auth::id();
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Procesa validación para solicitudes FormData (método original)
     */
    protected function prepareFormDataValidation(): void
    {
        $requestData = $this->all();
        $mergeData = [];

        // ✅ Procesar arrays que pueden venir en diferentes formatos
        foreach (['colors', 'sizes', 'tags'] as $field) {
            if (isset($requestData[$field])) {
                $value = $requestData[$field];

                // Si ya es un array, no hacer nada
                if (is_array($value)) {
                    // Filtrar valores vacíos y limpiar
                    $mergeData[$field] = array_filter(array_map('trim', $value));
                } elseif (is_string($value) && ! empty($value)) {
                    // Si es string, intentar convertir a array
                    try {
                        // Intentar decodificar JSON
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $mergeData[$field] = array_filter(array_map('trim', $decoded));
                        } else {
                            // Si no es JSON, dividir por comas
                            $mergeData[$field] = array_filter(array_map('trim', explode(',', $value)));
                        }
                    } catch (\Exception $e) {
                        // Si falla, dividir por comas
                        $mergeData[$field] = array_filter(array_map('trim', explode(',', $value)));
                    }
                } else {
                    // Si está vacío, asignar array vacío
                    $mergeData[$field] = [];
                }
            }
        }

        // ✅ Procesar atributos como array de objetos
        if (isset($requestData['attributes'])) {
            $value = $requestData['attributes'];

            if (is_array($value)) {
                // Si ya es array, verificar formato
                $processedAttributes = [];
                foreach ($value as $attr) {
                    if (is_array($attr) && isset($attr['key']) && isset($attr['value'])) {
                        $processedAttributes[] = [
                            'key' => trim($attr['key']),
                            'value' => trim($attr['value']),
                        ];
                    }
                }
                $mergeData['attributes'] = $processedAttributes;
            } elseif (is_string($value) && ! empty($value)) {
                // Intentar decodificar JSON
                try {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $mergeData['attributes'] = $decoded;
                    } else {
                        $mergeData['attributes'] = [];
                    }
                } catch (\Exception $e) {
                    $mergeData['attributes'] = [];
                }
            } else {
                $mergeData['attributes'] = [];
            }
        }

        // ✅ Procesar booleanos correctamente
        foreach (['featured', 'published', 'replace_images'] as $field) {
            if (isset($requestData[$field])) {
                $value = $requestData[$field];

                // Convertir diferentes formatos a booleano
                if (is_string($value)) {
                    $mergeData[$field] = in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
                } elseif (is_numeric($value)) {
                    $mergeData[$field] = (bool) $value;
                } else {
                    $mergeData[$field] = (bool) $value;
                }
            }
        }

        // ✅ Procesar remove_images como array de enteros
        if (isset($requestData['remove_images'])) {
            $value = $requestData['remove_images'];

            if (is_array($value)) {
                $mergeData['remove_images'] = array_map('intval', array_filter($value));
            } elseif (is_string($value) && ! empty($value)) {
                try {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $mergeData['remove_images'] = array_map('intval', array_filter($decoded));
                    } else {
                        $mergeData['remove_images'] = array_map('intval', array_filter(explode(',', $value)));
                    }
                } catch (\Exception $e) {
                    $mergeData['remove_images'] = [];
                }
            } else {
                $mergeData['remove_images'] = [];
            }
        }

        // ✅ Asegurar user_id
        if (! isset($requestData['user_id'])) {
            $mergeData['user_id'] = Auth::id();
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validated data from the request.
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        // Asegurar que user_id esté incluido
        if (! isset($validated['user_id'])) {
            $validated['user_id'] = Auth::id();
        }

        if ($key === null) {
            return $validated;
        }

        return data_get($validated, $key, $default);
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verificar permisos
            $user = Auth::user();
            if ($user && ! $user->isSeller() && ! $user->isAdmin()) {
                $validator->errors()->add('authorization', 'No tienes permisos para gestionar productos');
            }
        });
    }

    /**
     * ✅ Método helper para obtener datos de request de forma segura
     *
     * @param  mixed  $default
     * @return mixed
     */
    protected function getRequestInput(string $key, $default = null)
    {
        return $this->input($key, $default);
    }

    /**
     * ✅ Método helper para verificar si existe un campo
     */
    protected function hasRequestInput(string $key): bool
    {
        return $this->has($key);
    }
}
