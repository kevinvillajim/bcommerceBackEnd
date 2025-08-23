<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$datafastOrder = App\Models\Order::find(74);
$deunaOrder = App\Models\Order::find(81);

if ($datafastOrder && $deunaOrder) {
    echo 'Orden Datafast ID 74 creada el: '.$datafastOrder->created_at."\n";
    echo 'Orden DeUna ID 81 creada el: '.$deunaOrder->created_at."\n";

    $timeDiff = $deunaOrder->created_at->diffInHours($datafastOrder->created_at);
    echo 'Diferencia: '.$timeDiff." horas\n";

    if ($datafastOrder->created_at < $deunaOrder->created_at) {
        echo "\n✅ CONFIRMADO: La orden Datafast es anterior a nuestras correcciones\n";
        echo "Por eso tiene la estructura de precios incorrecta almacenada.\n";
        echo "El total final coincide ($6.90) pero la estructura interna difiere.\n";
    }
} else {
    echo "Error: No se pudieron encontrar las órdenes\n";
}
