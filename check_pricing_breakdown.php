<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 ANALIZANDO PRICING_BREAKDOWN DE DEUNA\n";
echo "======================================\n";

$deunaOrder = \App\Models\Order::where('payment_method', 'deuna')
    ->where('user_id', 25)
    ->orderBy('created_at', 'desc')
    ->first();

if (! $deunaOrder) {
    echo "❌ No se encontró orden DeUna\n";
    exit;
}

echo "📦 Orden DeUna: {$deunaOrder->order_number}\n";
echo "💰 Campo 'total' directo: $".number_format($deunaOrder->total, 2)."\n\n";

if ($deunaOrder->pricing_breakdown) {
    echo "📊 CONTENIDO DE PRICING_BREAKDOWN:\n";
    $breakdown = json_decode($deunaOrder->pricing_breakdown, true);

    foreach ($breakdown as $key => $value) {
        $displayValue = is_numeric($value) ? '$'.number_format($value, 2) : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
        echo "  {$key}: {$displayValue}\n";
    }

    echo "\n🚨 PROBLEMA IDENTIFICADO:\n";
    if (isset($breakdown['final_total']) && $breakdown['final_total'] == 0) {
        echo "❌ pricing_breakdown.final_total = $0.00 (INCORRECTO)\n";
    }
    if (isset($breakdown['total']) && $breakdown['total'] == 6.90) {
        echo "✅ pricing_breakdown.total = $6.90 (CORRECTO)\n";
    }

    echo "\n💡 SOLUCIÓN:\n";
    echo "El frontend está usando 'final_total' pero debería usar 'total'\n";
    echo "O corregir el backend para que 'final_total' tenga el valor correcto\n";

} else {
    echo "❌ No hay pricing_breakdown\n";
}

echo "\n🔧 ANÁLISIS DETALLADO:\n";
echo "Frontend lee: pricingData.final_total ?? order.total\n";
echo "pricingData.final_total = $0.00 (se usa este)\n";
echo "order.total = $6.90 (se ignora)\n";
echo "Resultado mostrado: $0.00 + IVA $0.90 + Shipping $0.25? = $1.15\n";
