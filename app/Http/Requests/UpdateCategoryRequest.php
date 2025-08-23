<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo permitir a usuarios administradores
        return auth()->check() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->route('id') ?? $this->route('category');

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categoryId),
                'regex:/^[a-z0-9\-]+$/i', // Solo alfanuméricos y guiones
            ],
            'description' => 'nullable|string|max:1000',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    // No permitir ciclos en la jerarquía
                    if ($value == $categoryId) {
                        $fail('Una categoría no puede ser su propio padre.');
                    }

                    // Verificar que no esté intentando asignar como padre a uno de sus descendientes
                    // (esto requeriría una consulta más compleja que no implementaremos aquí)
                },
            ],
            'icon' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio',
            'name.max' => 'El nombre no puede exceder los 255 caracteres',
            'slug.required' => 'El slug es obligatorio',
            'slug.unique' => 'Este slug ya está en uso',
            'slug.regex' => 'El slug solo puede contener letras, números y guiones',
            'parent_id.exists' => 'La categoría padre seleccionada no existe',
            'order.integer' => 'El orden debe ser un número entero',
            'order.min' => 'El orden debe ser mayor o igual a cero',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $updates = [];

        // Si se actualiza el nombre pero no el slug, regenerar el slug
        if ($this->has('name') && ! $this->has('slug')) {
            $updates['slug'] = Str::slug($this->input('name'));
        }

        // Convertir valores booleanos
        $booleanFields = ['is_active', 'featured'];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $updates[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Aplicar actualizaciones
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }
}
