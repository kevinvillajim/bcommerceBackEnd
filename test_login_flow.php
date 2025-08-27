<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🔍 SIMULACIÓN COMPLETA DEL FLUJO DE LOGIN\n";
echo "========================================\n";

// Probar con usuario 24 (Kevin - suspended)
$userId = 24;
$user = \App\Models\User::find($userId);

if (!$user) {
    echo "❌ Usuario $userId no encontrado\n";
    exit(1);
}

echo "👤 Usuario encontrado:\n";
echo "   - ID: {$user->id}\n";
echo "   - Name: {$user->name}\n";
echo "   - Email: {$user->email}\n";
echo "   - Is Blocked: " . ($user->is_blocked ? 'Yes' : 'No') . "\n\n";

// Verificar seller
$seller = \App\Models\Seller::where('user_id', $user->id)->first();

if (!$seller) {
    echo "❌ No hay seller asociado al usuario\n";
    exit(1);
}

echo "🏪 Seller encontrado:\n";
echo "   - ID: {$seller->id}\n";
echo "   - Store: {$seller->store_name}\n";
echo "   - Status: {$seller->status}\n";
echo "   - Verification Level: {$seller->verification_level}\n\n";

// Simular lógica del AuthenticatedSessionController
echo "🔄 SIMULANDO LÓGICA DEL LOGIN:\n";
echo "-----------------------------\n";

if ($seller && in_array($seller->status, ['suspended', 'inactive'])) {
    echo "✅ Seller tiene status problemático: {$seller->status}\n";
    
    // Determinar tipo de notificación
    $notificationType = $seller->status === 'suspended' ? 'seller_suspended' : 'seller_inactive';
    echo "📝 Tipo de notificación a crear: {$notificationType}\n";
    
    // Verificar notificaciones existentes
    $unreadNotification = \App\Models\Notification::where('user_id', $user->id)
        ->where('type', $notificationType)
        ->where('read', false)
        ->first();
    
    if ($unreadNotification) {
        echo "ℹ️ YA EXISTE notificación no leída (ID: {$unreadNotification->id})\n";
        echo "   - Title: {$unreadNotification->title}\n";
        echo "   - Message: {$unreadNotification->message}\n";
        echo "   - Created: {$unreadNotification->created_at}\n";
        echo "❌ NO se creará nueva notificación\n\n";
    } else {
        echo "✅ NO hay notificación no leída, procediendo a crear...\n";
        
        // Preparar mensajes
        if ($seller->status === 'suspended') {
            $title = 'Cuenta de vendedor suspendida';
            $message = 'Tu cuenta de vendedor ha sido suspendida. Puedes ver tus datos históricos pero no realizar nuevas ventas. Contacta al administrador para más información.';
        } else {
            $title = 'Cuenta de vendedor desactivada';
            $message = 'Tu cuenta de vendedor ha sido desactivada. Contacta al administrador para reactivar tu cuenta.';
        }
        
        echo "📝 Datos de la notificación:\n";
        echo "   - Title: {$title}\n";
        echo "   - Message: {$message}\n";
        echo "   - Type: {$notificationType}\n\n";
        
        // Intentar crear la notificación
        try {
            $notification = \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => $notificationType,
                'title' => $title,
                'message' => $message,
                'read' => false,
                'data' => [
                    'seller_status' => $seller->status,
                    'store_name' => $seller->store_name
                ]
            ]);
            
            echo "✅ NOTIFICACIÓN CREADA EXITOSAMENTE:\n";
            echo "   - ID: {$notification->id}\n";
            echo "   - User ID: {$notification->user_id}\n";
            echo "   - Type: {$notification->type}\n";
            echo "   - Title: {$notification->title}\n";
            echo "   - Read: " . ($notification->read ? 'Yes' : 'No') . "\n";
            echo "   - Created: {$notification->created_at}\n";
            echo "   - Data: " . json_encode($notification->data) . "\n\n";
            
        } catch (Exception $e) {
            echo "❌ ERROR AL CREAR NOTIFICACIÓN:\n";
            echo "   - Error: {$e->getMessage()}\n";
            echo "   - File: {$e->getFile()}:{$e->getLine()}\n";
            echo "   - Trace: {$e->getTraceAsString()}\n\n";
        }
    }
} else {
    if (!$seller) {
        echo "❌ No hay seller asociado\n";
    } else {
        echo "ℹ️ Seller status es '{$seller->status}' - No requiere notificación\n";
    }
}

// Verificar estado final
echo "📊 ESTADO FINAL:\n";
echo "--------------\n";

$finalNotifications = \App\Models\Notification::where('user_id', $user->id)
    ->whereIn('type', ['seller_suspended', 'seller_inactive'])
    ->get();

echo "Total notificaciones de seller status para usuario {$user->id}: {$finalNotifications->count()}\n";

foreach ($finalNotifications as $notif) {
    echo "- ID {$notif->id}: {$notif->type} - {$notif->title} - Read: " . ($notif->read ? 'Yes' : 'No') . "\n";
}