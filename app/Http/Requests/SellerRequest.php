<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Anyone can apply to be a seller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_name' => 'required|string|min:3|max:100|unique:sellers,store_name',
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'store_name.required' => 'A store name is required',
            'store_name.unique' => 'This store name is already taken',
            'store_name.min' => 'Store name must be at least 3 characters',
            'store_name.max' => 'Store name cannot exceed 100 characters',
            'description.max' => 'Description cannot exceed 500 characters',
        ];
    }
}
