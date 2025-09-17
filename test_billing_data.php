<?php

require_once __DIR__.'/vendor/autoload.php';

// Inicializar Laravel correctamente
$app = require_once __DIR__.'/bootstrap/app.php';
$app->boot();

echo "🧪 ETAPA 1 - TEST: Verificando campo billing_data en modelo Order\n";

try {
    $order = App\Models\Order::first();

    if ($order) {
        echo '✅ Orden encontrada ID: '.$order->id."\n";
        echo '✅ billing_data existe: '.(array_key_exists('billing_data', $order->getAttributes()) ? 'SI' : 'NO')."\n";
        echo '✅ billing_data valor: '.(is_null($order->billing_data) ? 'NULL' : 'CON DATOS')."\n";
        echo '✅ shipping_data existe: '.(is_null($order->shipping_data) ? 'NO' : 'SI')."\n";

        // Verificar que está en fillable
        echo '✅ billing_data en fillable: '.(in_array('billing_data', $order->getFillable()) ? 'SI' : 'NO')."\n";

        // Verificar cast
        $casts = $order->getCasts();
        echo '✅ billing_data cast: '.(isset($casts['billing_data']) ? $casts['billing_data'] : 'NO DEFINIDO')."\n";
    } else {
        echo "⚠️ No hay órdenes en la base de datos para testear\n";
    }
} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
}

echo "\n🏁 Test ETAPA 1 completado\n";
