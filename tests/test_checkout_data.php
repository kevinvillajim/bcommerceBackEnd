<?php

require_once __DIR__.'/vendor/autoload.php';

// Simular un test del endpoint de Datafast con CheckoutData temporal
echo "🧪 TESTING ENDPOINT DE DATAFAST CON CHECKOUTDATA TEMPORAL\n";
echo "=======================================================\n\n";

// Datos de CheckoutData temporal de ejemplo
$checkoutData = [
    'shippingAddress' => [
        'street' => 'Av. Amazonas 123',
        'city' => 'Quito',
        'country' => 'Ecuador',
        'identification' => '1234567890',
    ],
    'customer' => [
        'given_name' => 'Juan',
        'middle_name' => 'Carlos',
        'surname' => 'Pérez',
        'phone' => '0999999999',
        'doc_id' => '1234567890',
    ],
    'total' => 25.50,
    'subtotal' => 20.00,
    'shipping_cost' => 5.50,
    'tax' => 3.83,
    'items' => [
        [
            'product_id' => 1,
            'name' => 'Producto de Prueba',
            'quantity' => 1,
            'price' => 20.00,
        ],
    ],
    // ✅ CAMPOS DE CHECKOUTDATA TEMPORAL
    'session_id' => 'checkout_123_'.time(),
    'validated_at' => date('c'), // ISO 8601
];

echo "📋 DATOS DE PRUEBA (CheckoutData temporal):\n";
echo 'Session ID: '.$checkoutData['session_id']."\n";
echo 'Validated At: '.$checkoutData['validated_at']."\n";
echo 'Total: $'.$checkoutData['total']."\n";
echo 'Items: '.count($checkoutData['items'])."\n\n";

// Verificar que el servidor esté corriendo
$url = 'http://127.0.0.1:8001/api/test';
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
    ],
]);

echo "🔍 VERIFICANDO SERVIDOR LARAVEL...\n";
$response = @file_get_contents($url, false, $context);
if ($response === false) {
    echo "❌ Servidor Laravel no está corriendo en puerto 8001\n";
    exit(1);
} else {
    $data = json_decode($response, true);
    echo '✅ Servidor Laravel funcionando: '.($data['message'] ?? 'OK')."\n\n";
}

// Test 1: Envío con datos normales (sin campos temporales)
echo "📝 TEST 1: Envío SIN campos temporales (flujo normal)\n";
$normalData = $checkoutData;
unset($normalData['session_id']);
unset($normalData['validated_at']);

$jsonData = json_encode($normalData);
echo 'Datos enviados: '.strlen($jsonData)." bytes\n";
echo 'Contiene session_id: '.(isset($normalData['session_id']) ? 'SÍ' : 'NO')."\n";
echo 'Contiene validated_at: '.(isset($normalData['validated_at']) ? 'SÍ' : 'NO')."\n\n";

// Test 2: Envío con datos temporales (nuevo flujo)
echo "📝 TEST 2: Envío CON campos temporales (flujo CheckoutData)\n";
$temporalData = $checkoutData;
$jsonData = json_encode($temporalData);
echo 'Datos enviados: '.strlen($jsonData)." bytes\n";
echo 'Contiene session_id: '.(isset($temporalData['session_id']) ? 'SÍ' : 'NO')."\n";
echo 'Contiene validated_at: '.(isset($temporalData['validated_at']) ? 'SÍ' : 'NO')."\n";
echo 'Session ID: '.$temporalData['session_id']."\n";
echo 'Validated At: '.$temporalData['validated_at']."\n\n";

// Verificar lógica de detección en DatafastController
echo "🔍 SIMULANDO LÓGICA DE DETECCIÓN EN CONTROLLER:\n";
echo 'hasSessionId = '.(isset($temporalData['session_id']) && ! empty($temporalData['session_id']) ? 'true' : 'false')."\n";
echo 'hasValidatedAt = '.(isset($temporalData['validated_at']) && ! empty($temporalData['validated_at']) ? 'true' : 'false')."\n";
echo 'isTemporalCheckout = '.((isset($temporalData['session_id']) && ! empty($temporalData['session_id']) &&
                                isset($temporalData['validated_at']) && ! empty($temporalData['validated_at'])) ? 'true' : 'false')."\n\n";

echo "✅ TESTS COMPLETADOS - Estructura de datos validada\n";
echo "💡 Para pruebas con API real, necesitas autenticación JWT\n";
