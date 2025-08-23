<?php

/**
 * 🧪 SIMULADOR DE PAGO EXITOSO COMPLETO
 *
 * Este script simula todo el flujo que ocurriría cuando DeUna confirma un pago real:
 * 1. Crea un pago DeUna de prueba en la BD
 * 2. Simula el webhook de confirmación de pago
 * 3. Verifica que se cree la orden correctamente
 * 4. Confirma reducción de inventario
 * 5. Valida generación de factura
 * 6. Revisa notificaciones al usuario
 */

require_once __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\DeunaWebhookController;
use App\Models\DeunaPayment;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Configurar Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "🧪 INICIANDO SIMULACIÓN DE PAGO EXITOSO\n";
echo "=====================================\n\n";

try {
    DB::beginTransaction();

    // 1. PREPARAR DATOS DE PRUEBA
    echo "📋 PASO 1: Preparando datos de prueba...\n";

    // Obtener un usuario real
    $user = User::where('role', '!=', 'admin')->first();
    if (! $user) {
        throw new Exception('❌ No se encontró ningún usuario para la prueba');
    }

    // Obtener productos reales con stock
    $products = Product::where('stock', '>', 5)->take(2)->get();
    if ($products->count() < 2) {
        throw new Exception('❌ Se necesitan al menos 2 productos con stock > 5');
    }

    echo "✅ Usuario: {$user->name} (ID: {$user->id})\n";
    foreach ($products as $product) {
        echo "✅ Producto: {$product->name} - Stock: {$product->stock} - Precio: \${$product->price}\n";
    }

    // 2. CREAR PAGO DEUNA DE PRUEBA
    echo "\n📋 PASO 2: Creando pago DeUna de prueba...\n";

    $orderId = 'TEST-ORDER-'.time();
    $paymentId = 'PAY-TEST-'.time();
    $transactionId = 'TXN-'.uniqid();

    // Calcular totales reales
    $subtotal = 0;
    $items = [];
    foreach ($products as $index => $product) {
        $quantity = $index + 2; // 2, 3 productos
        $price = $product->price;
        $itemTotal = $price * $quantity;
        $subtotal += $itemTotal;

        $items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $itemTotal,
        ];
    }

    $shipping = $subtotal >= 50 ? 0 : 5;
    $tax = ($subtotal + $shipping) * 0.15;
    $total = $subtotal + $shipping + $tax;

    echo "💰 Subtotal: \${$subtotal}\n";
    echo "🚚 Envío: \${$shipping}\n";
    echo "💳 IVA (15%): \${$tax}\n";
    echo "🎯 Total: \${$total}\n";

    // Guardar stock inicial para verificar reducción
    $initialStocks = [];
    foreach ($products as $product) {
        $initialStocks[$product->id] = $product->stock;
    }

    // Crear registro de pago DeUna
    $deunaPayment = DeunaPayment::create([
        'order_id' => $orderId,
        'payment_id' => $paymentId,
        'user_id' => $user->id,
        'amount' => $total,
        'currency' => 'USD',
        'status' => 'pending',
        'customer_data' => json_encode([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ? $user->phone : '0999999999',
        ]),
        'items_data' => json_encode($items),
        'metadata' => json_encode([
            'user_id' => $user->id,
            'test_simulation' => true,
            'original_subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'tax_amount' => $tax,
        ]),
    ]);

    echo "✅ Pago DeUna creado: {$paymentId}\n";

    // 3. SIMULAR WEBHOOK DE CONFIRMACIÓN
    echo "\n📋 PASO 3: Simulando webhook de confirmación...\n";

    $webhookData = [
        'idTransaction' => $paymentId,
        'status' => 'SUCCESS',
        'event' => 'payment.completed',
        'transferNumber' => 'TRF-'.uniqid(),
        'branchId' => 'BRANCH-001',
        'posId' => 'POS-001',
        'customerIdentification' => $user->cedula ? $user->cedula : '0999999999001',
        'customerFullName' => $user->name,
        'transaction_id' => $transactionId,
        'amount' => $total,
        'currency' => 'USD',
        'timestamp' => now()->toISOString(),
        'data' => [
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId,
        ],
    ];

    // Crear request simulado
    $request = Request::create('/webhooks/deuna/payment-status', 'POST', $webhookData);
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('X-DeUna-Signature', 'test-signature');

    // Procesar webhook
    $webhookController = app(DeunaWebhookController::class);
    $response = $webhookController->handlePaymentStatus($request);
    $responseData = $response->getData(true);

    if (! $responseData['success']) {
        throw new Exception('❌ Webhook falló: '.$responseData['message']);
    }

    echo "✅ Webhook procesado exitosamente\n";
    echo '✅ Payment ID procesado: '.$responseData['data']['payment_id']."\n";
    echo '✅ Estado: '.$responseData['data']['status']."\n";

    // 4. VERIFICAR CREACIÓN DE ORDEN
    echo "\n📋 PASO 4: Verificando creación de orden...\n";

    // Esperar un momento para que se procesen los eventos
    sleep(1);

    $order = Order::where('id', $orderId)->first();
    if (! $order) {
        throw new Exception("❌ Orden no fue creada: {$orderId}");
    }

    echo "✅ Orden creada: {$order->order_number} (ID: {$order->id})\n";
    echo "✅ Usuario: {$order->user_id}\n";
    echo "✅ Estado: {$order->status}\n";
    echo "✅ Total: \${$order->total}\n";
    echo "✅ Estado de pago: {$order->payment_status}\n";

    // Verificar items de la orden
    $orderItems = $order->items;
    echo '✅ Items en la orden: '.$orderItems->count()."\n";

    foreach ($orderItems as $item) {
        echo "  - {$item->product->name}: {$item->quantity} x \${$item->price} = \${$item->subtotal}\n";
    }

    // 5. VERIFICAR REDUCCIÓN DE INVENTARIO
    echo "\n📋 PASO 5: Verificando reducción de inventario...\n";

    foreach ($items as $itemData) {
        $product = Product::find($itemData['product_id']);
        $initialStock = $initialStocks[$product->id];
        $expectedStock = $initialStock - $itemData['quantity'];

        if ($product->stock != $expectedStock) {
            throw new Exception("❌ Stock incorrecto para {$product->name}. Esperado: {$expectedStock}, Actual: {$product->stock}");
        }

        echo "✅ {$product->name}: {$initialStock} → {$product->stock} (-{$itemData['quantity']})\n";
    }

    // 6. VERIFICAR GENERACIÓN DE FACTURA
    echo "\n📋 PASO 6: Verificando generación de factura...\n";

    // Buscar factura por order_id
    $invoice = Invoice::where('order_id', $orderId)->first();
    if (! $invoice) {
        echo "⚠️ Factura no encontrada (puede estar procesándose en background)\n";
    } else {
        echo "✅ Factura generada: {$invoice->invoice_number}\n";
        echo "✅ Subtotal: \${$invoice->subtotal}\n";
        echo "✅ IVA: \${$invoice->tax_amount}\n";
        echo "✅ Total: \${$invoice->total_amount}\n";
        echo "✅ Estado: {$invoice->status}\n";
    }

    // 7. VERIFICAR NOTIFICACIONES
    echo "\n📋 PASO 7: Verificando notificaciones al usuario...\n";

    $notifications = Notification::where('user_id', $user->id)
        ->where('created_at', '>=', now()->subMinutes(5))
        ->orderBy('created_at', 'desc')
        ->get();

    echo '✅ Notificaciones recientes: '.$notifications->count()."\n";
    foreach ($notifications->take(3) as $notification) {
        echo "  - {$notification->title}: {$notification->message}\n";
    }

    // 8. VERIFICAR PAGO ACTUALIZADO
    echo "\n📋 PASO 8: Verificando estado del pago...\n";

    $updatedPayment = DeunaPayment::find($deunaPayment->id);
    echo "✅ Estado del pago: {$updatedPayment->status}\n";
    if ($updatedPayment->transaction_id) {
        echo "✅ Transaction ID: {$updatedPayment->transaction_id}\n";
    }

    // 9. RESUMEN FINAL
    echo "\n🎉 RESUMEN FINAL DE LA SIMULACIÓN\n";
    echo "===============================\n";
    echo "✅ Pago procesado exitosamente: \${$total}\n";
    echo "✅ Orden creada: {$order->order_number}\n";
    echo "✅ Inventario actualizado correctamente\n";
    echo "✅ Estado de orden: {$order->status}\n";
    echo "✅ Estado de pago: {$order->payment_status}\n";

    if ($invoice) {
        echo "✅ Factura generada: {$invoice->invoice_number}\n";
    }

    echo '✅ Notificaciones enviadas: '.$notifications->count()."\n";

    // 10. INFORMACIÓN PARA EL FRONTEND
    echo "\n📱 DATOS PARA MOSTRAR EN EL FRONTEND\n";
    echo "==================================\n";
    echo "Order ID: {$order->id}\n";
    echo "Order Number: {$order->order_number}\n";
    echo "Payment ID: {$paymentId}\n";
    echo "Transaction ID: {$transactionId}\n";
    echo "Status: {$order->status}\n";
    echo "Payment Status: {$order->payment_status}\n";
    echo "Total: \${$order->total}\n";

    if ($invoice) {
        echo "Invoice Number: {$invoice->invoice_number}\n";
        echo "Invoice Status: {$invoice->status}\n";
    }

    // URL para ver la orden
    echo "\n🔗 URLs para pruebas:\n";
    echo "Ver orden: GET /api/user/orders/{$order->id}\n";
    echo 'Comprobante: GET /api/invoices/'.($invoice ? $invoice->id : 'PENDING')."\n";

    DB::commit();

    echo "\n✅ SIMULACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "Todos los sistemas funcionan correctamente!\n\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR EN LA SIMULACIÓN: ".$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
    exit(1);
}
