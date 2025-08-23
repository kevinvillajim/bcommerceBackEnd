<?php

namespace App\UseCases\Cart;

use App\Models\DiscountCode;
use App\Services\PricingService;

class ApplyCartDiscountCodeUseCase
{
    private PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Validate and apply a discount code to cart totals
     */
    public function execute(string $code, array $cartItems, int $userId): array
    {
        // Validate the discount code
        $discountCode = DiscountCode::where('code', $code)->first();

        if (! $discountCode) {
            return [
                'success' => false,
                'message' => 'Código de descuento inválido',
                'data' => null,
            ];
        }

        if (! $discountCode->isValid()) {
            $message = $discountCode->is_used
                ? 'Este código de descuento ya ha sido utilizado'
                : 'Este código de descuento ha expirado';

            return [
                'success' => false,
                'message' => $message,
                'data' => null,
            ];
        }

        // 🔧 FIXED: Check ownership based on discount code type
        if ($discountCode->feedback_id !== null) {
            // Cupón de feedback: debe pertenecer al usuario
            $feedback = $discountCode->feedback;
            if (! $feedback || $feedback->user_id !== $userId) {
                return [
                    'success' => false,
                    'message' => 'Este código de descuento no es válido para tu cuenta',
                    'data' => null,
                ];
            }
        }
        // Cupones de admin (feedback_id = null) pueden ser usados por cualquier usuario

        // Calculate cart totals without discount code
        $originalTotals = $this->pricingService->calculateCheckoutTotals($cartItems);

        // ✅ FIXED: Aplicar código de descuento sobre precio YA DESCONTADO (con descuentos de seller y volumen)
        $subtotalWithDiscounts = $originalTotals['totals']['subtotal_products']; // Ya incluye descuentos de seller y volumen
        $discountPercentage = $discountCode->discount_percentage;

        // ✅ Cálculo exacto: trabajar con centavos para evitar errores de redondeo
        $subtotalCents = round($subtotalWithDiscounts * 100);
        $discountCents = round(($subtotalCents * $discountPercentage) / 100);
        $newSubtotalCents = $subtotalCents - $discountCents;

        // Convertir de vuelta a dólares con precisión exacta
        $discountAmount = $discountCents / 100;
        $newSubtotal = $newSubtotalCents / 100;

        $shippingCost = $originalTotals['totals']['shipping_cost'];

        // 🔧 CORREGIDO: IVA se calcula sobre base gravable (subtotal + envío)
        $ivaRate = 0.15; // Get from config if needed
        $taxableBaseCents = $newSubtotalCents + round($shippingCost * 100); // Base gravable en centavos
        $ivaAmountCents = round($taxableBaseCents * $ivaRate);
        $newIvaAmount = $ivaAmountCents / 100;

        $newFinalTotal = $newSubtotal + $shippingCost + $newIvaAmount;

        // Create new totals array with standardized structure
        $discountedTotals = $originalTotals['totals'];
        $discountedTotals['subtotal_products'] = $newSubtotal;
        $discountedTotals['subtotal_final'] = $newSubtotal + $shippingCost; // 🔧 AGREGADO: Base gravable
        $discountedTotals['iva_amount'] = $newIvaAmount;
        $discountedTotals['final_total'] = $newFinalTotal;
        $discountedTotals['feedback_discount_amount'] = $discountAmount;
        $discountedTotals['feedback_discount_percentage'] = $discountPercentage;
        $discountedTotals['total_discounts'] += $discountAmount;

        // Update breakdown
        $discountedBreakdown = $originalTotals['breakdown'];
        $discountedBreakdown['subtotal_con_descuentos'] = $newSubtotal;
        $discountedBreakdown['iva'] = $newIvaAmount;
        $discountedBreakdown['pagado'] = $newFinalTotal;
        $discountedBreakdown['total_ahorrado'] = $discountedBreakdown['total_ahorrado'] + $discountAmount;
        $discountedBreakdown['ahorro_cupon_feedback'] = $discountAmount;

        return [
            'success' => true,
            'message' => 'Código de descuento aplicado correctamente',
            'data' => [
                'discount_code' => [
                    'code' => $discountCode->code,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'expires_at' => $discountCode->expires_at,
                ],
                'totals' => $discountedTotals,
                'breakdown' => $discountedBreakdown,
                'original_totals' => $originalTotals['totals'],
                'items_by_seller' => $originalTotals['items_by_seller'],
                'processed_items' => $originalTotals['processed_items'],
            ],
        ];
    }

    /**
     * Mark discount code as used
     */
    public function markAsUsed(string $code, int $userId): array
    {
        $discountCode = DiscountCode::where('code', $code)->first();

        if (! $discountCode) {
            return [
                'success' => false,
                'message' => 'Código de descuento no encontrado',
            ];
        }

        if ($discountCode->is_used) {
            return [
                'success' => false,
                'message' => 'Este código ya ha sido utilizado',
            ];
        }

        // Mark as used
        $discountCode->update([
            'is_used' => true,
            'used_by' => $userId,
            'used_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Código de descuento marcado como usado',
        ];
    }

    /**
     * Validate a discount code without applying it
     */
    public function validateOnly(string $code, array $cartItems, int $userId): array
    {
        // Validate the discount code
        $discountCode = DiscountCode::where('code', $code)->first();

        if (! $discountCode) {
            return [
                'success' => false,
                'message' => 'Código de descuento inválido',
            ];
        }

        if (! $discountCode->isValid()) {
            $message = $discountCode->is_used
                ? 'Este código de descuento ya ha sido utilizado'
                : 'Este código de descuento ha expirado';

            return [
                'success' => false,
                'message' => $message,
            ];
        }

        // 🔧 FIXED: Check ownership based on discount code type
        if ($discountCode->feedback_id !== null) {
            // Cupón de feedback: debe pertenecer al usuario
            $feedback = $discountCode->feedback;
            if (! $feedback || $feedback->user_id !== $userId) {
                return [
                    'success' => false,
                    'message' => 'Este código de descuento no es válido para tu cuenta',
                ];
            }
        }
        // Cupones de admin (feedback_id = null) pueden ser usados por cualquier usuario

        // ✅ FIXED: Calculate potential discount sobre precio ya descontado
        $originalTotals = $this->pricingService->calculateCheckoutTotals($cartItems);
        $subtotalWithDiscounts = $originalTotals['totals']['subtotal_products']; // Ya incluye descuentos aplicados

        // Cálculo exacto con centavos
        $subtotalCents = round($subtotalWithDiscounts * 100);
        $discountCents = round(($subtotalCents * $discountCode->discount_percentage) / 100);
        $discountAmount = $discountCents / 100;

        return [
            'success' => true,
            'message' => 'Código de descuento válido',
            'data' => [
                'code' => $discountCode->code,
                'discount_percentage' => $discountCode->discount_percentage,
                'discount_amount' => $discountAmount,
                'expires_at' => $discountCode->expires_at,
            ],
        ];
    }
}
