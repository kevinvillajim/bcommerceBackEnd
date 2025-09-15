<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
            // Reglas de pago
            'payment' => 'required|array',
            'payment.method' => 'required|string|in:credit_card,debit_card,paypal,datafast,de_una,transfer',
            'payment.amount' => 'sometimes|numeric|min:0.01',

            // Datos específicos de tarjeta de crédito/débito
            'payment.card_number' => 'required_if:payment.method,credit_card,debit_card|string|regex:/^\d{13,19}$/',
            'payment.expiry_month' => 'required_if:payment.method,credit_card,debit_card|integer|between:1,12',
            'payment.expiry_year' => 'required_if:payment.method,credit_card,debit_card|integer|min:'.date('Y'),
            'payment.cvv' => 'required_if:payment.method,credit_card,debit_card|string|regex:/^\d{3,4}$/',
            'payment.card_holder_name' => 'required_if:payment.method,credit_card,debit_card|string|max:255',

            // Datos específicos de PayPal
            'payment.paypal_email' => 'required_if:payment.method,paypal|email|max:255',

            // Datos específicos de transferencia bancaria
            'payment.bank_account' => 'required_if:payment.method,bank_transfer|string|max:255',
            'payment.bank_routing' => 'required_if:payment.method,bank_transfer|string|max:255',

            // ✅ CORREGIDO: Validaciones para shippingAddress (no shipping)
            'shippingAddress' => 'required|array',
            'shippingAddress.name' => 'required|string|max:255',
            'shippingAddress.identification' => 'required|string|min:10|max:13|regex:/^\d{10}(\d{3})?$/',
            'shippingAddress.street' => 'required|string|max:500',
            'shippingAddress.city' => 'required|string|max:100',
            'shippingAddress.state' => 'required|string|max:100',
            'shippingAddress.postalCode' => 'sometimes|nullable|string|max:20',
            'shippingAddress.country' => 'required|string|max:100',
            'shippingAddress.phone' => 'required|string|max:20',

            // ✅ NUEVO: Validaciones para billingAddress
            'billingAddress' => 'required|array',
            'billingAddress.name' => 'required|string|max:255',
            'billingAddress.identification' => 'required|string|min:10|max:13|regex:/^\d{10}(\d{3})?$/',
            'billingAddress.street' => 'required|string|max:500',
            'billingAddress.city' => 'required|string|max:100',
            'billingAddress.state' => 'required|string|max:100',
            'billingAddress.postalCode' => 'sometimes|nullable|string|max:20',
            'billingAddress.country' => 'required|string|max:100',
            'billingAddress.phone' => 'required|string|max:20',

            // ✅ NUEVO: Validación para items con precios
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.price' => 'required|numeric|min:0.01',

            // Campos opcionales adicionales
            'volume_discounts' => 'sometimes|array',
            'seller_id' => 'sometimes|integer|exists:users,id',
            'discount_code' => 'sometimes|string|max:20',

            // ✅ NUEVO: Totales calculados del frontend
            'calculated_totals' => 'required|array',
            'calculated_totals.subtotal' => 'required|numeric|min:0',
            'calculated_totals.tax' => 'required|numeric|min:0',
            'calculated_totals.shipping' => 'required|numeric|min:0',
            'calculated_totals.total' => 'required|numeric|min:0.01',
            'calculated_totals.total_discounts' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment.required' => 'La información de pago es requerida.',
            'payment.method.required' => 'El método de pago es requerido.',
            'payment.method.in' => 'El método de pago debe ser uno de: credit_card, debit_card, paypal, datafast, de_una, transfer.',

            'payment.card_number.required_if' => 'El número de tarjeta es requerido para pagos con tarjeta.',
            'payment.card_number.regex' => 'El número de tarjeta debe tener entre 13 y 19 dígitos.',
            'payment.expiry_month.required_if' => 'El mes de vencimiento es requerido para pagos con tarjeta.',
            'payment.expiry_month.between' => 'El mes de vencimiento debe estar entre 1 y 12.',
            'payment.expiry_year.required_if' => 'El año de vencimiento es requerido para pagos con tarjeta.',
            'payment.expiry_year.min' => 'El año de vencimiento no puede ser anterior al año actual.',
            'payment.cvv.required_if' => 'El código CVV es requerido para pagos con tarjeta.',
            'payment.cvv.regex' => 'El código CVV debe tener 3 o 4 dígitos.',
            'payment.card_holder_name.required_if' => 'El nombre del titular de la tarjeta es requerido.',

            'payment.paypal_email.required_if' => 'El email de PayPal es requerido para pagos con PayPal.',
            'payment.paypal_email.email' => 'Debe proporcionar un email válido de PayPal.',

            'payment.bank_account.required_if' => 'El número de cuenta bancaria es requerido para transferencias.',
            'payment.bank_routing.required_if' => 'El código de routing bancario es requerido para transferencias.',

            // ✅ CORREGIDO: Mensajes para shippingAddress y billingAddress
            'shippingAddress.required' => 'La información de envío es requerida.',
            'shippingAddress.name.required' => 'El nombre del receptor es requerido.',
            'shippingAddress.identification.required' => 'La cédula/RUC del receptor es requerida.',
            'shippingAddress.identification.min' => 'La cédula debe tener al menos 10 dígitos.',
            'shippingAddress.identification.max' => 'El RUC no puede tener más de 13 dígitos.',
            'shippingAddress.identification.regex' => 'La cédula debe tener 10 dígitos o RUC 13 dígitos terminando en 001.',
            'shippingAddress.phone.required' => 'El teléfono del receptor es requerido.',
            'shippingAddress.street.required' => 'La dirección de envío es requerida.',
            'shippingAddress.city.required' => 'La ciudad de envío es requerida.',
            'shippingAddress.state.required' => 'El estado/provincia de envío es requerido.',
            'shippingAddress.country.required' => 'El país de envío es requerido.',

            'billingAddress.required' => 'La información de facturación es requerida.',
            'billingAddress.name.required' => 'El nombre para facturación es requerido.',
            'billingAddress.identification.required' => 'La cédula/RUC para facturación es requerida.',
            'billingAddress.identification.min' => 'La cédula debe tener al menos 10 dígitos.',
            'billingAddress.identification.max' => 'El RUC no puede tener más de 13 dígitos.',
            'billingAddress.identification.regex' => 'La cédula debe tener 10 dígitos o RUC 13 dígitos terminando en 001.',
            'billingAddress.phone.required' => 'El teléfono para facturación es requerido.',
            'billingAddress.street.required' => 'La dirección de facturación es requerida.',
            'billingAddress.city.required' => 'La ciudad de facturación es requerida.',
            'billingAddress.state.required' => 'El estado/provincia de facturación es requerido.',
            'billingAddress.country.required' => 'El país de facturación es requerido.',

            // ✅ NUEVOS: Mensajes para items
            'items.required' => 'Los items del carrito son requeridos.',
            'items.min' => 'Debe haber al menos un item en el carrito.',
            'items.*.product_id.required' => 'El ID del producto es requerido.',
            'items.*.product_id.integer' => 'El ID del producto debe ser un número entero.',
            'items.*.product_id.exists' => 'El producto especificado no existe.',
            'items.*.quantity.required' => 'La cantidad es requerida.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min' => 'La cantidad mínima es 1.',
            'items.*.quantity.max' => 'La cantidad máxima es 100.',
            'items.*.price.required' => 'El precio del item es requerido.',
            'items.*.price.numeric' => 'El precio debe ser un número válido.',
            'items.*.price.min' => 'El precio debe ser mayor a 0.',

            // ✅ NUEVOS: Mensajes para calculated_totals
            'calculated_totals.required' => 'Los totales calculados son requeridos.',
            'calculated_totals.subtotal.required' => 'El subtotal es requerido.',
            'calculated_totals.tax.required' => 'El impuesto es requerido.',
            'calculated_totals.shipping.required' => 'El costo de envío es requerido.',
            'calculated_totals.total.required' => 'El total es requerido.',
            'calculated_totals.total_discounts.required' => 'El total de descuentos es requerido.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que si se proporciona un monto específico, coincida con el total del carrito
            if ($this->filled('payment.amount')) {
                /** @var float $providedAmount */
                $providedAmount = (float) $this->input('payment.amount');

                // Aquí podrías agregar lógica para validar contra el total real del carrito
                // Por ejemplo:
                // $cartTotal = $this->getCartTotal();
                // if (abs($providedAmount - $cartTotal) > 0.01) {
                //     $validator->errors()->add('payment.amount', 'El monto no coincide con el total del carrito.');
                // }
            }

            // ✅ NUEVO: Validar que todos los items tengan precios válidos
            if ($this->filled('items') && is_array($this->input('items'))) {
                $items = $this->input('items');
                foreach ($items as $index => $item) {
                    if (isset($item['price']) && $item['price'] <= 0) {
                        $validator->errors()->add("items.{$index}.price", 'El precio debe ser mayor a 0.');
                    }

                    if (isset($item['quantity']) && $item['quantity'] <= 0) {
                        $validator->errors()->add("items.{$index}.quantity", 'La cantidad debe ser mayor a 0.');
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear datos antes de la validación
        /** @var array $paymentData */
        $paymentData = $this->input('payment', []);

        // Limpiar número de tarjeta (remover espacios y guiones)
        if (isset($paymentData['card_number'])) {
            $paymentData['card_number'] = preg_replace('/[\s\-]/', '', $paymentData['card_number']);
        }

        // Limpiar CVV
        if (isset($paymentData['cvv'])) {
            $paymentData['cvv'] = preg_replace('/\D/', '', $paymentData['cvv']);
        }

        // Convertir meses y años a enteros
        if (isset($paymentData['expiry_month'])) {
            $paymentData['expiry_month'] = (int) $paymentData['expiry_month'];
        }

        if (isset($paymentData['expiry_year'])) {
            $paymentData['expiry_year'] = (int) $paymentData['expiry_year'];
        }

        // ✅ NUEVO: Limpiar y formatear items
        $items = $this->input('items', []);
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                // Asegurar que product_id sea entero
                if (isset($item['product_id'])) {
                    $items[$index]['product_id'] = (int) $item['product_id'];
                }

                // Asegurar que quantity sea entero
                if (isset($item['quantity'])) {
                    $items[$index]['quantity'] = (int) $item['quantity'];
                }

                // Asegurar que price sea float
                if (isset($item['price'])) {
                    $items[$index]['price'] = (float) $item['price'];
                }
            }
        }

        $this->merge([
            'payment' => $paymentData,
            'items' => $items,
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'payment.method' => 'método de pago',
            'payment.card_number' => 'número de tarjeta',
            'payment.expiry_month' => 'mes de vencimiento',
            'payment.expiry_year' => 'año de vencimiento',
            'payment.cvv' => 'código CVV',
            'payment.card_holder_name' => 'nombre del titular',
            'payment.paypal_email' => 'email de PayPal',
            'payment.bank_account' => 'cuenta bancaria',
            'payment.bank_routing' => 'código de routing',
            'shipping.first_name' => 'nombre',
            'shipping.last_name' => 'apellido',
            'shipping.email' => 'email',
            'shipping.phone' => 'teléfono',
            'shipping.address' => 'dirección',
            'shipping.city' => 'ciudad',
            'shipping.state' => 'estado/provincia',
            'shipping.postal_code' => 'código postal',
            'shipping.country' => 'país',
            'shipping.identification' => 'cédula/RUC',
            'shipping.notes' => 'notas adicionales',
            'items' => 'items del carrito',
            'items.*.product_id' => 'ID del producto',
            'items.*.quantity' => 'cantidad',
            'items.*.price' => 'precio',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        // Log errores de validación para debugging (opcional)
        \Illuminate\Support\Facades\Log::warning('Checkout validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['payment.card_number', 'payment.cvv']), // No loggear datos sensibles
        ]);

        // Llamar al comportamiento por defecto
        parent::failedValidation($validator);
    }

    /**
     * ✅ MÉTODO HELPER: Validar si el checkout tiene descuentos por volumen
     */
    private function hasVolumeDiscounts(): bool
    {
        if (! $this->filled('volume_discounts')) {
            return false;
        }

        /** @var array $volumeDiscounts */
        $volumeDiscounts = $this->input('volume_discounts', []);

        return ! empty($volumeDiscounts);
    }

    /**
     * ✅ NUEVO: Obtener items validados y formateados
     */
    public function getValidatedItems(): array
    {
        $items = $this->validated()['items'] ?? [];

        return array_map(function ($item) {
            return [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
            ];
        }, $items);
    }

    /**
     * ✅ NUEVO: Obtener totales calculados del frontend
     */
    public function getCalculatedTotals(): array
    {
        $totals = $this->validated()['calculated_totals'] ?? [];

        return [
            'subtotal' => (float) ($totals['subtotal'] ?? 0),
            'tax' => (float) ($totals['tax'] ?? 0),
            'shipping' => (float) ($totals['shipping'] ?? 0),
            'total' => (float) ($totals['total'] ?? 0),
            'total_discounts' => (float) ($totals['total_discounts'] ?? 0),
        ];
    }
}
