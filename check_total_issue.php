<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 VERIFICANDO PROBLEMA DEL TOTAL EN FRONTEND\n";
echo "==========================================\n";

// Obtener las órdenes más recientes
$deunaOrder = \App\Models\Order::where('payment_method', 'deuna')
    ->where('user_id', 25)
    ->orderBy('created_at', 'desc')
    ->first();

$datafastOrder = \App\Models\Order::where('payment_method', 'datafast')
    ->where('user_id', 25)
    ->orderBy('created_at', 'desc')
    ->first();

if (! $deunaOrder || ! $datafastOrder) {
    echo "❌ ERROR: No se encontraron las órdenes\n";
    exit;
}

echo "📦 ÓRDENES ANALIZADAS:\n";
echo "DeUna: ID {$deunaOrder->id} - {$deunaOrder->order_number}\n";
echo "Datafast: ID {$datafastOrder->id} - {$datafastOrder->order_number}\n\n";

echo "🔍 COMPARACIÓN DE CAMPO 'TOTAL' EN BASE DE DATOS:\n";
echo 'DeUna total: $'.number_format($deunaOrder->total, 2)."\n";
echo 'Datafast total: $'.number_format($datafastOrder->total, 2)."\n";

if (abs($deunaOrder->total - $datafastOrder->total) < 0.01) {
    echo "✅ Los totales en DB son idénticos\n";
} else {
    echo "❌ Los totales en DB son diferentes\n";
}

echo "\n🔍 VERIFICACIÓN DETALLADA DE ORDEN DEUNA:\n";
echo 'order_number: '.$deunaOrder->order_number."\n";
echo 'total: $'.number_format($deunaOrder->total, 2)."\n";
echo 'subtotal_products: $'.number_format($deunaOrder->subtotal_products, 2)."\n";
echo 'shipping_cost: $'.number_format($deunaOrder->shipping_cost, 2)."\n";
echo 'iva_amount: $'.number_format($deunaOrder->iva_amount, 2)."\n";
echo 'original_total: $'.number_format($deunaOrder->original_total, 2)."\n";
echo 'seller_discount_savings: $'.number_format($deunaOrder->seller_discount_savings ?? 0, 2)."\n";

echo "\n🧮 CÁLCULO MANUAL PARA VERIFICAR:\n";
$manualTotal = $deunaOrder->subtotal_products + $deunaOrder->shipping_cost + $deunaOrder->iva_amount;
echo 'subtotal_products + shipping_cost + iva_amount = $'.number_format($manualTotal, 2)."\n";

if (abs($manualTotal - $deunaOrder->total) < 0.01) {
    echo "✅ El cálculo manual coincide con el total almacenado\n";
} else {
    echo "❌ El cálculo manual NO coincide con el total almacenado\n";
    echo 'Diferencia: $'.number_format(abs($manualTotal - $deunaOrder->total), 2)."\n";
}

echo "\n🔍 VERIFICACIÓN DE PRICING_BREAKDOWN:\n";
if ($deunaOrder->pricing_breakdown) {
    $breakdown = json_decode($deunaOrder->pricing_breakdown, true);
    echo 'Breakdown total: $'.number_format($breakdown['total'] ?? 0, 2)."\n";
    echo 'Breakdown final_total: $'.number_format($breakdown['final_total'] ?? 0, 2)."\n";

    if (isset($breakdown['total']) && abs($breakdown['total'] - $deunaOrder->total) < 0.01) {
        echo "✅ Breakdown total coincide con order total\n";
    } else {
        echo "❌ Breakdown total NO coincide con order total\n";
    }
} else {
    echo "❌ No hay pricing_breakdown\n";
}

echo "\n🔍 POSIBLES CAUSAS DEL PROBLEMA:\n";
echo "1. El frontend está leyendo un campo incorrecto\n";
echo "2. Hay algún cálculo adicional en el frontend que está alterando el total\n";
echo "3. El frontend está usando 'subtotal_products' en lugar de 'total'\n";
echo "4. Problema con el formato de número o conversión\n";

echo "\n💡 VALORES QUE EL FRONTEND DEBERÍA MOSTRAR:\n";
echo 'Total pagado (DeUna): $'.number_format($deunaOrder->total, 2)."\n";
echo 'Total pagado (Datafast): $'.number_format($datafastOrder->total, 2)."\n";

if ($deunaOrder->total == 1.15) {
    echo "\n🚨 PROBLEMA IDENTIFICADO: El total está guardado como $1.15 en lugar de $6.90\n";
    echo "Esto indica un problema en el cálculo del HandleDeunaWebhookUseCase\n";
} elseif ($deunaOrder->total == 6.90) {
    echo "\n🚨 PROBLEMA IDENTIFICADO: El total está correcto en DB ($6.90)\n";
    echo "El problema está en el frontend, que está mostrando un valor incorrecto\n";
}
