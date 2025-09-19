<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;

// Simular una petición al endpoint de configuraciones
echo "=== TEST: Validación del Header Banner ===\n\n";

echo "1. Datos que devuelve el endpoint:\n";
echo "================================\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/configurations/unified');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);

    echo "✅ Endpoint funciona correctamente\n";
    echo "Status: " . $data['status'] . "\n";

    $shipping = $data['data']['shipping'];
    echo "\nConfiguraciones de shipping:\n";
    echo "- enabled: " . ($shipping['enabled'] ? 'true' : 'false') . "\n";
    echo "- free_threshold: " . $shipping['free_threshold'] . "\n";
    echo "- default_cost: " . $shipping['default_cost'] . "\n";

    echo "\n2. Lógica del Header (ANTES de la corrección):\n";
    echo "============================================\n";

    // Simular lógica anterior (con ||)
    $freeThresholdOld = $shipping['free_threshold'] ?: 50;
    $shippingEnabledOld = $shipping['enabled'] ?: true;

    echo "Con operador || (INCORRECTO):\n";
    echo "- freeThreshold = " . $freeThresholdOld . "\n";
    echo "- shippingEnabled = " . ($shippingEnabledOld ? 'true' : 'false') . "\n";

    if ($shippingEnabledOld) {
        echo "Banner mostraría: 'Envío gratis en pedidos superiores a $" . $freeThresholdOld . "'\n";
    } else {
        echo "Banner mostraría: '🎉 ¡Envío GRATIS por tiempo limitado!'\n";
    }

    echo "\n3. Lógica del Header (DESPUÉS de la corrección):\n";
    echo "===============================================\n";

    // Simular lógica nueva (con ??)
    $freeThresholdNew = $shipping['free_threshold'] ?? 50;
    $shippingEnabledNew = $shipping['enabled'] ?? true;

    echo "Con operador ?? (CORRECTO):\n";
    echo "- freeThreshold = " . $freeThresholdNew . "\n";
    echo "- shippingEnabled = " . ($shippingEnabledNew ? 'true' : 'false') . "\n";

    if ($shippingEnabledNew) {
        echo "Banner mostraría: 'Envío gratis en pedidos superiores a $" . $freeThresholdNew . "'\n";
    } else {
        echo "Banner mostraría: '🎉 ¡Envío GRATIS por tiempo limitado!'\n";
    }

    echo "\n4. Verificación del fix:\n";
    echo "======================\n";

    if (!$shippingEnabledOld && $shippingEnabledNew) {
        echo "❌ La corrección NO funciona como esperado\n";
    } elseif (!$shippingEnabledOld && !$shippingEnabledNew) {
        echo "✅ La corrección funciona CORRECTAMENTE\n";
        echo "El banner ahora mostrará el mensaje de envío gratis correcto\n";
    }

} else {
    echo "❌ Error al consultar endpoint: HTTP $httpCode\n";
}

echo "\n=== FIN DEL TEST ===\n";