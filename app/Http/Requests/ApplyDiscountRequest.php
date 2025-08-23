<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6',
            'product_id' => 'required|integer|exists:products,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Please provide a discount code',
            'code.size' => 'Discount code must be 6 characters long',
            'product_id.required' => 'Please specify a product',
            'product_id.exists' => 'Selected product does not exist',
        ];
    }
}
