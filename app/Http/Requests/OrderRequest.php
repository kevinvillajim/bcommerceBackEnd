<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controlador
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seller_id' => 'sometimes|nullable|exists:sellers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'sometimes|numeric|min:0',
            'items.*.subtotal' => 'sometimes|numeric|min:0',
            'total' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string|in:pending,processing,paid,shipped,delivered,cancelled',
            'payment_id' => 'sometimes|nullable|string',
            'payment_method' => 'sometimes|nullable|string|in:credit_card,paypal,transfer,other',
            'payment_status' => 'sometimes|nullable|string|in:pending,completed,failed',
            'shipping_data' => 'sometimes|nullable|array',
            'shipping_data.address' => 'required_with:shipping_data|string',
            'shipping_data.city' => 'required_with:shipping_data|string',
            'shipping_data.state' => 'required_with:shipping_data|string',
            'shipping_data.country' => 'required_with:shipping_data|string',
            'shipping_data.postal_code' => 'required_with:shipping_data|string',
            'shipping_data.name' => 'sometimes|nullable|string',
            'shipping_data.phone' => 'sometimes|nullable|string',
            'order_number' => 'sometimes|nullable|string|unique:orders,order_number',
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
            'items.required' => 'Debe especificar al menos un producto en el pedido',
            'items.min' => 'Debe incluir al menos un producto en el pedido',
            'items.*.product_id.required' => 'El ID del producto es obligatorio',
            'items.*.product_id.exists' => 'El producto seleccionado no existe',
            'items.*.quantity.required' => 'La cantidad es obligatoria',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1',
            'shipping_data.address.required_with' => 'La dirección de envío es obligatoria',
            'shipping_data.city.required_with' => 'La ciudad de envío es obligatoria',
            'shipping_data.state.required_with' => 'La provincia/estado de envío es obligatorio',
            'shipping_data.country.required_with' => 'El país de envío es obligatorio',
            'shipping_data.postal_code.required_with' => 'El código postal de envío es obligatorio',
        ];
    }
}
