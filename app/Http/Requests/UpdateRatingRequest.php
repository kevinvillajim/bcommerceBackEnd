<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => 'sometimes|required|numeric|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'status' => 'sometimes|string|in:pending,approved,rejected,flagged',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'La valoración es obligatoria',
            'rating.numeric' => 'La valoración debe ser un número',
            'rating.min' => 'La valoración mínima es 1',
            'rating.max' => 'La valoración máxima es 5',
            'title.max' => 'El título no puede superar los 255 caracteres',
            'comment.max' => 'El comentario no puede superar los 1000 caracteres',
            'status.in' => 'El estado proporcionado no es válido',
        ];
    }
}
