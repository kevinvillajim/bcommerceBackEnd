<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\AccountingAccount;
use App\Models\AccountingTransaction;

// Bootstrapping Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Iniciando corrección de tipos contables...\n\n";

// ========================================
// 1. CORREGIR CUENTAS CONTABLES
// ========================================

echo "📊 PASO 1: Corrigiendo tipos de cuentas contables\n";
echo "------------------------------------------------\n";

// Mapeo de códigos a tipos correctos
$accountTypeMappings = [
    // ACTIVOS (1xxx)
    '1101' => 'ASSET',   // Efectivo y Equivalentes
    '1201' => 'ASSET',   // Cuentas por Cobrar
    '1301' => 'ASSET',   // Inventario de Productos

    // PASIVOS (2xxx)
    '2101' => 'LIABILITY', // Cuentas por Pagar
    '2301' => 'LIABILITY', // IVA por Pagar
    '2401' => 'LIABILITY', // Comisiones por Pagar

    // PATRIMONIO (3xxx)
    '3101' => 'EQUITY',    // Capital Social
    '3201' => 'EQUITY',    // Utilidades Retenidas

    // INGRESOS (4xxx)
    '4101' => 'REVENUE',   // Ingresos por Ventas
    '4201' => 'REVENUE',   // Ingresos por Envío
    '4301' => 'REVENUE',   // Ingresos por Comisiones

    // GASTOS (5xxx)
    '5101' => 'EXPENSE',   // Gastos Operativos
    '5201' => 'EXPENSE',   // Gastos de Marketing
    '5301' => 'EXPENSE',   // Gastos de Tecnología
    '5401' => 'EXPENSE',   // Gastos Bancarios

    // COSTOS (6xxx) - también van como EXPENSE
    '6101' => 'EXPENSE',   // Costo de Productos Vendidos
    '6201' => 'EXPENSE',   // Costo de Envío
    '6301' => 'EXPENSE',   // Costo de Procesamiento de Pagos
];

$accountsUpdated = 0;
$accountsSkipped = 0;

foreach ($accountTypeMappings as $code => $correctType) {
    $account = AccountingAccount::where('code', $code)->first();

    if (!$account) {
        echo "⚠️  Cuenta {$code} no encontrada, saltando...\n";
        $accountsSkipped++;
        continue;
    }

    $currentType = $account->type;

    if ($currentType === $correctType) {
        echo "✅ Cuenta {$code} ({$account->name}) ya tiene el tipo correcto: {$correctType}\n";
        $accountsSkipped++;
        continue;
    }

    // Actualizar el tipo
    $account->update(['type' => $correctType]);
    echo "🔄 Cuenta {$code} ({$account->name}): '{$currentType}' → '{$correctType}'\n";
    $accountsUpdated++;
}

echo "\n📈 Resumen cuentas: {$accountsUpdated} actualizadas, {$accountsSkipped} sin cambios\n\n";

// ========================================
// 2. CORREGIR TRANSACCIONES
// ========================================

echo "💰 PASO 2: Corrigiendo tipos de transacciones\n";
echo "----------------------------------------------\n";

// Buscar transacciones con tipos vacíos
$transactionsWithEmptyType = AccountingTransaction::where('type', '')->get();

echo "📊 Encontradas {$transactionsWithEmptyType->count()} transacciones con tipo vacío\n\n";

$transactionsUpdated = 0;

foreach ($transactionsWithEmptyType as $transaction) {
    $newType = null;

    // Lógica para determinar el tipo basado en contexto
    if ($transaction->order_id) {
        // Si tiene order_id, es una venta
        $newType = 'SALE';
        echo "🛍️  Transacción {$transaction->id} (Orden #{$transaction->order_id}): '' → 'SALE'\n";
    } else {
        // Transacción manual - analizar descripción
        $description = strtolower($transaction->description);

        if (strpos($description, 'venta') !== false) {
            $newType = 'SALE';
            echo "💵 Transacción {$transaction->id} ('{$transaction->description}'): '' → 'SALE'\n";
        } elseif (strpos($description, 'gasto') !== false || strpos($description, 'pago') !== false) {
            $newType = 'EXPENSE';
            echo "💸 Transacción {$transaction->id} ('{$transaction->description}'): '' → 'EXPENSE'\n";
        } else {
            // Por defecto, marcar como ajuste
            $newType = 'ADJUSTMENT';
            echo "⚖️  Transacción {$transaction->id} ('{$transaction->description}'): '' → 'ADJUSTMENT'\n";
        }
    }

    if ($newType) {
        $transaction->update(['type' => $newType]);
        $transactionsUpdated++;
    }
}

echo "\n📈 Resumen transacciones: {$transactionsUpdated} actualizadas\n\n";

// ========================================
// 3. VERIFICACIÓN FINAL
// ========================================

echo "🔍 PASO 3: Verificación final\n";
echo "-----------------------------\n";

// Verificar cuentas
$accountsWithEmptyType = AccountingAccount::where('type', '')->count();
echo "📊 Cuentas con tipo vacío restantes: {$accountsWithEmptyType}\n";

// Verificar transacciones
$transactionsWithEmptyType = AccountingTransaction::where('type', '')->count();
echo "💰 Transacciones con tipo vacío restantes: {$transactionsWithEmptyType}\n";

echo "\n✅ ¡Corrección completada!\n";
echo "=====================================\n";
echo "📊 Total cuentas actualizadas: {$accountsUpdated}\n";
echo "💰 Total transacciones actualizadas: {$transactionsUpdated}\n";
echo "📋 Cuentas con tipo vacío restantes: {$accountsWithEmptyType}\n";
echo "📋 Transacciones con tipo vacío restantes: {$transactionsWithEmptyType}\n";

if ($accountsWithEmptyType === 0 && $transactionsWithEmptyType === 0) {
    echo "\n🎉 ¡Todos los tipos han sido corregidos exitosamente!\n";
} else {
    echo "\n⚠️  Aún quedan algunos registros con tipos vacíos para revisar manualmente.\n";
}