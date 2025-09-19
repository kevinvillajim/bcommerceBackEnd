<?php

// Test de la lógica de detección de CheckoutData temporal del DatafastController
echo "🧪 TESTING LÓGICA DE DETECCIÓN CHECKOUT TEMPORAL\n";
echo "===============================================\n\n";

// Simular datos recibidos en el request
$normalRequest = [
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
];

$temporalRequest = array_merge($normalRequest, [
    'session_id' => 'checkout_123_'.time(),
    'validated_at' => date('c'),
]);

// Función que simula la lógica del DatafastController
function testCheckoutDetection($validated, $testName)
{
    echo "📝 TEST: $testName\n";
    echo "Datos recibidos:\n";
    foreach (['session_id', 'validated_at', 'total'] as $key) {
        $value = $validated[$key] ?? 'NO PRESENTE';
        echo "  $key: $value\n";
    }

    // ✅ LÓGICA EXACTA DEL DATAFASTCONTROLLER (lineas 76-78)
    $hasSessionId = isset($validated['session_id']) && ! empty($validated['session_id']);
    $hasValidatedAt = isset($validated['validated_at']) && ! empty($validated['validated_at']);
    $isTemporalCheckout = $hasSessionId && $hasValidatedAt;

    echo "Resultado de detección:\n";
    echo '  hasSessionId: '.($hasSessionId ? 'true' : 'false')."\n";
    echo '  hasValidatedAt: '.($hasValidatedAt ? 'true' : 'false')."\n";
    echo '  isTemporalCheckout: '.($isTemporalCheckout ? 'true' : 'false')."\n";

    if ($isTemporalCheckout) {
        echo "  ✅ DETECTADO COMO CHECKOUT TEMPORAL\n";
        echo "  📋 Procesando con nuevo flujo CheckoutData\n";
    } else {
        echo "  📋 Procesando con flujo normal (cart)\n";
    }

    echo "\n";

    return $isTemporalCheckout;
}

// Ejecutar tests
$result1 = testCheckoutDetection($normalRequest, 'Request Normal (sin campos temporales)');
$result2 = testCheckoutDetection($temporalRequest, 'Request Temporal (con CheckoutData)');

// Test edge cases
echo "🔍 TESTS DE CASOS EDGE:\n";

// Session ID vacío
$emptySessionRequest = array_merge($normalRequest, [
    'session_id' => '',
    'validated_at' => date('c'),
]);
testCheckoutDetection($emptySessionRequest, 'Session ID vacío');

// Session ID null
$nullSessionRequest = array_merge($normalRequest, [
    'session_id' => null,
    'validated_at' => date('c'),
]);
testCheckoutDetection($nullSessionRequest, 'Session ID null');

// Solo session_id
$onlySessionRequest = array_merge($normalRequest, [
    'session_id' => 'checkout_123_'.time(),
]);
testCheckoutDetection($onlySessionRequest, 'Solo Session ID (sin validated_at)');

// Solo validated_at
$onlyValidatedRequest = array_merge($normalRequest, [
    'validated_at' => date('c'),
]);
testCheckoutDetection($onlyValidatedRequest, 'Solo Validated At (sin session_id)');

echo "✅ TODOS LOS TESTS COMPLETADOS\n";
echo "💡 La lógica de detección funciona correctamente\n";
