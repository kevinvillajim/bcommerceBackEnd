<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
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
            'rating' => 'required|numeric|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'order_id' => 'nullable|integer|exists:orders,id',
            'seller_id' => 'required_without:product_id|integer|exists:sellers,id',
            'product_id' => 'required_without:seller_id|integer|exists:products,id',
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
            'order_id.exists' => 'La orden especificada no existe',
            'seller_id.required_without' => 'Debe especificar un vendedor o un producto',
            'seller_id.exists' => 'El vendedor especificado no existe',
            'product_id.required_without' => 'Debe especificar un vendedor o un producto',
            'product_id.exists' => 'El producto especificado no existe',
        ];
    }
}
