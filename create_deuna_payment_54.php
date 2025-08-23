<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::find(25);
$product = App\Models\Product::find(54);

if (! $user) {
    echo "ERROR: Usuario 25 no encontrado\n";
    exit;
}

if (! $product) {
    echo "ERROR: Producto 54 no encontrado\n";
    exit;
}

$orderId = 'TEST-PROD54-'.time();
$paymentId = 'PAY-PROD54-'.time();

echo "CREANDO PAGO DEUNA PARA:\n";
echo 'Usuario: '.$user->name.' (ID: '.$user->id.")\n";
echo 'Producto: '.$product->name.' (ID: '.$product->id.")\n";
echo 'Precio original: $'.$product->price."\n";
echo 'Descuento: '.$product->discount_percentage."%\n";

$items = [
    [
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 1,
        'price' => $product->price, // Precio original, el descuento se aplicará en el webhook
    ],
];

$deunaPayment = App\Models\DeunaPayment::create([
    'order_id' => $orderId,
    'payment_id' => $paymentId,
    'amount' => 6.90,
    'currency' => 'USD',
    'status' => 'pending',
    'customer' => [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '0999999999',
    ],
    'items' => $items,
    'metadata' => [
        'user_id' => $user->id,
        'test_producto_54' => true,
    ],
]);

echo "\nPAGO DEUNA CREADO:\n";
echo 'ID: '.$deunaPayment->id."\n";
echo 'Order ID: '.$deunaPayment->order_id."\n";
echo 'Payment ID: '.$deunaPayment->payment_id."\n";
echo 'Amount: $'.$deunaPayment->amount."\n";

// Guardar IDs para el webhook
file_put_contents('webhook_test_data.txt', json_encode([
    'order_id' => $orderId,
    'payment_id' => $paymentId,
    'amount' => 6.90,
]));

echo "\nDatos guardados en webhook_test_data.txt para el webhook\n";
