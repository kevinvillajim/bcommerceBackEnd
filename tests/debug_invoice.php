<?php

require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Verificar estructura de la tabla invoices
echo "=== ESTRUCTURA TABLA INVOICES ===\n";
$columns = DB::select('DESCRIBE invoices');
foreach($columns as $col) {
    if (strpos($col->Field, 'email') !== false) {
        echo "Campo: {$col->Field} - Tipo: {$col->Type}\n";
    }
}

// Verificar factura 72
echo "\n=== FACTURA 72 ===\n";
$invoice = App\Models\Invoice::find(72);
if ($invoice) {
    echo "ID: {$invoice->id}\n";
    echo "customer_email: {$invoice->customer_email}\n";
    echo "email_sent_at: " . ($invoice->email_sent_at ?? 'NULL') . "\n";

    // Verificar usuario asociado
    $user = $invoice->user;
    if ($user) {
        echo "user->email: {$user->email}\n";
    }
} else {
    echo "Factura 72 no encontrada\n";
}

echo "\nDebug completado.\n";