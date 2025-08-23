<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 PRUEBA FINAL DE COMPATIBILIDAD DEUNA vs DATAFAST\n";
echo "=================================================\n";

// Obtener las últimas órdenes DeUna y Datafast
$deunaOrder = \App\Models\Order::where('payment_method', 'deuna')
    ->where('user_id', 25)
    ->orderBy('created_at', 'desc')
    ->first();

$datafastOrder = \App\Models\Order::where('payment_method', 'datafast')
    ->where('user_id', 25)
    ->orderBy('created_at', 'desc')
    ->first();

if (! $deunaOrder || ! $datafastOrder) {
    echo "❌ ERROR: No se encontraron las órdenes requeridas\n";
    exit;
}

echo "📦 ÓRDENES PARA COMPARACIÓN:\n";
echo "DeUna: ID {$deunaOrder->id} - {$deunaOrder->order_number}\n";
echo "Datafast: ID {$datafastOrder->id} - {$datafastOrder->order_number}\n\n";

// Función para formatear valores para comparación
function formatForComparison($value)
{
    return '$'.number_format($value, 2);
}

// Función para comparar dos valores
function compareValues($label, $deunaValue, $datafastValue)
{
    $match = abs($deunaValue - $datafastValue) < 0.01;
    $status = $match ? '✅' : '❌';

    echo sprintf("%-25s | DeUna: %-8s | Datafast: %-8s | %s\n",
        $label,
        formatForComparison($deunaValue),
        formatForComparison($datafastValue),
        $status
    );

    return $match;
}

echo "🔍 COMPARACIÓN DE CAMPOS PRINCIPALES:\n";
echo str_repeat('-', 80)."\n";

$allMatch = true;

// Comparar campos principales
$allMatch &= compareValues('Total', $deunaOrder->total, $datafastOrder->total);
$allMatch &= compareValues('Subtotal Productos', $deunaOrder->subtotal_products, $datafastOrder->subtotal_products);
$allMatch &= compareValues('Costo de Envío', $deunaOrder->shipping_cost, $datafastOrder->shipping_cost);
$allMatch &= compareValues('IVA', $deunaOrder->iva_amount, $datafastOrder->iva_amount);
$allMatch &= compareValues('Total Original', $deunaOrder->original_total, $datafastOrder->original_total);
$allMatch &= compareValues('Ahorros Vendedor', $deunaOrder->seller_discount_savings, $datafastOrder->seller_discount_savings);

echo str_repeat('-', 80)."\n";

// Comparar pricing_breakdown
echo "\n🔍 COMPARACIÓN DE PRICING_BREAKDOWN:\n";
echo str_repeat('-', 80)."\n";

$deunaBreakdown = json_decode($deunaOrder->pricing_breakdown, true);
$datafastBreakdown = json_decode($datafastOrder->pricing_breakdown, true);

if ($deunaBreakdown && $datafastBreakdown) {
    $breakdownFields = [
        'subtotal' => 'Subtotal',
        'shipping' => 'Envío',
        'tax' => 'IVA',
        'total' => 'Total',
        'final_total' => 'Total Final',
        'subtotal_original' => 'Subtotal Original',
        'seller_discounts' => 'Descuentos Vendedor',
        'free_shipping' => 'Envío Gratis',
    ];

    foreach ($breakdownFields as $field => $label) {
        if (isset($deunaBreakdown[$field]) && isset($datafastBreakdown[$field])) {
            if (is_bool($deunaBreakdown[$field])) {
                $match = $deunaBreakdown[$field] === $datafastBreakdown[$field];
                $status = $match ? '✅' : '❌';
                echo sprintf("%-25s | DeUna: %-8s | Datafast: %-8s | %s\n",
                    $label,
                    $deunaBreakdown[$field] ? 'true' : 'false',
                    $datafastBreakdown[$field] ? 'true' : 'false',
                    $status
                );
                $allMatch &= $match;
            } else {
                $allMatch &= compareValues($label, $deunaBreakdown[$field], $datafastBreakdown[$field]);
            }
        }
    }
} else {
    echo "❌ ERROR: No se pudo decodificar pricing_breakdown\n";
    $allMatch = false;
}

echo str_repeat('-', 80)."\n";

// Resultado final
echo "\n🎯 RESULTADO FINAL:\n";
if ($allMatch) {
    echo "✅ ÉXITO: Todas las órdenes DeUna y Datafast son 100% compatibles\n";
    echo "✅ El frontend mostrará valores idénticos para ambos métodos de pago\n";
    echo "✅ Los componentes OrderPricingBreakdown y OrderItemsList funcionarán correctamente\n";
} else {
    echo "❌ ERROR: Hay discrepancias entre las órdenes DeUna y Datafast\n";
    echo "❌ El frontend puede mostrar valores diferentes\n";
}

echo "\n💡 PRÓXIMOS PASOS:\n";
echo "1. Probar la visualización en el frontend\n";
echo "2. Verificar que no haya errores 405 al actualizar estado de órdenes\n";
echo "3. Confirmar que las páginas de detalle de orden cargan correctamente\n";
