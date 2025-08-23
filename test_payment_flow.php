<?php

/**
 * 🧪 TEST DE FLUJO DE PAGO COMPLETO USANDO ARTISAN TINKER
 *
 * Este script usa el entorno de Laravel correctamente configurado
 */
$script = '
echo "🧪 INICIANDO TEST DE PAGO COMPLETO\\n";
echo "================================\\n\\n";

// Obtener datos necesarios
$user = App\\Models\\User::where("role", "!=", "admin")->first();
if (!$user) {
    echo "❌ No hay usuarios disponibles\\n";
    return;
}

$products = App\\Models\\Product::where("stock", ">", 5)->take(2)->get();
if ($products->count() < 2) {
    echo "❌ Se necesitan al menos 2 productos con stock > 5\\n";
    return;
}

echo "✅ Usuario: {$user->name} (ID: {$user->id})\\n";
foreach ($products as $product) {
    echo "✅ Producto: {$product->name} - Stock: {$product->stock} - Precio: \\${$product->price}\\n";
}

// Crear datos de pago
$orderId = "TEST-ORDER-" . time();
$paymentId = "PAY-TEST-" . time();
$transactionId = "TXN-" . uniqid();

// Calcular totales
$subtotal = 0;
$items = [];
foreach ($products as $index => $product) {
    $quantity = $index + 2; // 2, 3 productos
    $price = $product->price;
    $itemTotal = $price * $quantity;
    $subtotal += $itemTotal;
    
    $items[] = [
        "product_id" => $product->id,
        "name" => $product->name,
        "quantity" => $quantity,
        "price" => $price,
        "subtotal" => $itemTotal
    ];
}

$shipping = $subtotal >= 50 ? 0 : 5;
$tax = ($subtotal + $shipping) * 0.15;
$total = $subtotal + $shipping + $tax;

echo "💰 Subtotal: \\${$subtotal}\\n";
echo "🚚 Envío: \\${$shipping}\\n";
echo "💳 IVA (15%): \\${$tax}\\n";
echo "🎯 Total: \\${$total}\\n";

// Guardar stocks iniciales
$initialStocks = [];
foreach ($products as $product) {
    $initialStocks[$product->id] = $product->stock;
}

echo "\\n📋 PASO 1: Creando pago DeUna...\\n";

// Crear pago DeUna
$deunaPayment = App\\Models\\DeunaPayment::create([
    "order_id" => $orderId,
    "payment_id" => $paymentId,
    "user_id" => $user->id,
    "amount" => $total,
    "currency" => "USD",
    "status" => "pending",
    "customer_data" => json_encode([
        "name" => $user->name,
        "email" => $user->email,
        "phone" => $user->phone ?: "0999999999"
    ]),
    "items_data" => json_encode($items),
    "metadata" => json_encode([
        "user_id" => $user->id,
        "test_simulation" => true
    ])
]);

echo "✅ Pago DeUna creado: {$paymentId}\\n";

echo "\\n📋 PASO 2: Simulando webhook...\\n";

// Simular webhook data
$webhookData = [
    "idTransaction" => $paymentId,
    "status" => "SUCCESS",
    "event" => "payment.completed",
    "transaction_id" => $transactionId
];

// Usar el UseCase directamente
$useCase = app(App\\UseCases\\Payment\\HandleDeunaWebhookUseCase::class);
$result = $useCase->execute($webhookData);

echo "✅ Webhook procesado: " . ($result["success"] ? "SUCCESS" : "FAILED") . "\\n";
echo "✅ Payment ID: " . $result["payment_id"] . "\\n";
echo "✅ Status: " . $result["status"] . "\\n";

echo "\\n📋 PASO 3: Verificando orden creada...\\n";

$order = App\\Models\\Order::where("id", $orderId)->first();
if ($order) {
    echo "✅ Orden creada: {$order->order_number} (ID: {$order->id})\\n";
    echo "✅ Estado: {$order->status}\\n";
    echo "✅ Total: \\${$order->total}\\n";
    echo "✅ Estado de pago: {$order->payment_status}\\n";
    
    // Verificar items
    $orderItems = $order->items;
    echo "✅ Items: " . $orderItems->count() . "\\n";
    foreach ($orderItems as $item) {
        echo "  - {$item->product->name}: {$item->quantity} x \\${$item->price}\\n";
    }
} else {
    echo "❌ Orden no encontrada\\n";
}

echo "\\n📋 PASO 4: Verificando inventario...\\n";

foreach ($items as $itemData) {
    $product = App\\Models\\Product::find($itemData["product_id"]);
    $initialStock = $initialStocks[$product->id];
    $expectedStock = $initialStock - $itemData["quantity"];
    
    if ($product->stock == $expectedStock) {
        echo "✅ {$product->name}: {$initialStock} → {$product->stock} (-{$itemData["quantity"]})\\n";
    } else {
        echo "❌ {$product->name}: Stock incorrecto. Esperado: {$expectedStock}, Actual: {$product->stock}\\n";
    }
}

echo "\\n📋 PASO 5: Verificando factura...\\n";

$invoice = App\\Models\\Invoice::where("order_id", $orderId)->first();
if ($invoice) {
    echo "✅ Factura: {$invoice->invoice_number}\\n";
    echo "✅ Total: \\${$invoice->total_amount}\\n";
    echo "✅ Estado: {$invoice->status}\\n";
} else {
    echo "⚠️ Factura no encontrada (puede estar procesándose)\\n";
}

echo "\\n📋 PASO 6: Verificando notificaciones...\\n";

$notifications = App\\Models\\Notification::where("user_id", $user->id)
    ->where("created_at", ">=", now()->subMinutes(5))
    ->get();

echo "✅ Notificaciones recientes: " . $notifications->count() . "\\n";
foreach ($notifications->take(3) as $notification) {
    echo "  - {$notification->title}\\n";
}

echo "\\n🎉 RESUMEN FINAL\\n";
echo "==============\\n";
if ($order) {
    echo "✅ Orden: {$order->order_number}\\n";
    echo "✅ Estado: {$order->status}\\n";
    echo "✅ Pago: {$order->payment_status}\\n";
    echo "✅ Total: \\${$order->total}\\n";
    
    echo "\\n📱 URLs para frontend:\\n";
    echo "Ver orden: /api/user/orders/{$order->id}\\n";
    if ($invoice) {
        echo "Comprobante: /api/invoices/{$invoice->id}\\n";
    }
} else {
    echo "❌ Test falló - no se creó la orden\\n";
}

echo "\\n✅ TEST COMPLETADO\\n";
';

// Ejecutar usando php artisan tinker
file_put_contents('test_script.php', $script);
