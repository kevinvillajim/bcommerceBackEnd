<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug'),
                'regex:/^[a-z0-9\-]+$/i', // Solo alfanuméricos y guiones
            ],
            'description' => 'nullable|string|max:1000',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    // No permitir ciclos en la jerarquía
                    if ($value === $this->id) {
                        $fail('Una categoría no puede ser su propio padre.');
                    }
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

        // Si no se proporciona slug, generarlo a partir del nombre
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

        // Asignar valores por defecto
        if (! $this->has('is_active')) {
            $updates['is_active'] = true;
        }

        if (! $this->has('featured')) {
            $updates['featured'] = false;
        }

        if (! $this->has('order')) {
            $updates['order'] = 0;
        }

        // Aplicar actualizaciones
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }
}
