<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AccountingAccount;

class AccountingAccountsSeeder extends Seeder
{
    /**
     * ✅ Crea las cuentas contables básicas necesarias para el sistema BCommerce
     */
    public function run(): void
    {
        $accounts = [
            // ACTIVOS (ASSETS)
            [
                'code' => '1101',
                'name' => 'Efectivo y Equivalentes',
                'type' => 'ASSET',
                'description' => 'Dinero en efectivo y cuentas bancarias para operaciones diarias',
                'is_active' => true
            ],
            [
                'code' => '1201',
                'name' => 'Cuentas por Cobrar',
                'type' => 'ASSET',
                'description' => 'Cuentas pendientes de cobro a clientes',
                'is_active' => true
            ],
            [
                'code' => '1301',
                'name' => 'Inventario de Productos',
                'type' => 'ASSET',
                'description' => 'Valor del inventario de productos para la venta',
                'is_active' => true
            ],

            // PASIVOS (LIABILITIES)
            [
                'code' => '2101',
                'name' => 'Cuentas por Pagar',
                'type' => 'LIABILITY',
                'description' => 'Obligaciones pendientes con proveedores',
                'is_active' => true
            ],
            [
                'code' => '2301',
                'name' => 'IVA por Pagar',
                'type' => 'LIABILITY',
                'description' => 'Impuesto al Valor Agregado por pagar al SRI (Ecuador)',
                'is_active' => true
            ],
            [
                'code' => '2401',
                'name' => 'Comisiones por Pagar',
                'type' => 'LIABILITY',
                'description' => 'Comisiones pendientes de pago a vendedores',
                'is_active' => true
            ],

            // PATRIMONIO (EQUITY)
            [
                'code' => '3101',
                'name' => 'Capital Social',
                'type' => 'EQUITY',
                'description' => 'Capital inicial y aportaciones de socios',
                'is_active' => true
            ],
            [
                'code' => '3201',
                'name' => 'Utilidades Retenidas',
                'type' => 'EQUITY',
                'description' => 'Ganancias acumuladas no distribuidas',
                'is_active' => true
            ],

            // INGRESOS (REVENUE)
            [
                'code' => '4101',
                'name' => 'Ingresos por Ventas',
                'type' => 'REVENUE',
                'description' => 'Ingresos generados por ventas de productos en la plataforma',
                'is_active' => true
            ],
            [
                'code' => '4201',
                'name' => 'Ingresos por Envío',
                'type' => 'REVENUE',
                'description' => 'Ingresos por servicios de envío y logística',
                'is_active' => true
            ],
            [
                'code' => '4301',
                'name' => 'Ingresos por Comisiones',
                'type' => 'REVENUE',
                'description' => 'Comisiones cobradas a vendedores por transacciones',
                'is_active' => true
            ],

            // GASTOS (EXPENSES)
            [
                'code' => '5101',
                'name' => 'Gastos Operativos',
                'type' => 'EXPENSE',
                'description' => 'Gastos generales de operación de la plataforma',
                'is_active' => true
            ],
            [
                'code' => '5201',
                'name' => 'Gastos de Marketing',
                'type' => 'EXPENSE',
                'description' => 'Inversión en publicidad y promoción',
                'is_active' => true
            ],
            [
                'code' => '5301',
                'name' => 'Gastos de Tecnología',
                'type' => 'EXPENSE',
                'description' => 'Servidores, software y herramientas tecnológicas',
                'is_active' => true
            ],
            [
                'code' => '5401',
                'name' => 'Gastos Bancarios',
                'type' => 'EXPENSE',
                'description' => 'Comisiones y gastos de servicios financieros',
                'is_active' => true
            ],

            // COSTOS (EXPENSES)
            [
                'code' => '6101',
                'name' => 'Costo de Productos Vendidos',
                'type' => 'EXPENSE',
                'description' => 'Costo directo de los productos vendidos',
                'is_active' => true
            ],
            [
                'code' => '6201',
                'name' => 'Costo de Envío',
                'type' => 'EXPENSE',
                'description' => 'Costos directos de logística y envío',
                'is_active' => true
            ],
            [
                'code' => '6301',
                'name' => 'Costo de Procesamiento de Pagos',
                'type' => 'EXPENSE',
                'description' => 'Comisiones de gateways de pago (Datafast, PayPal, etc.)',
                'is_active' => true
            ]
        ];

        foreach ($accounts as $accountData) {
            AccountingAccount::firstOrCreate(
                ['code' => $accountData['code']],
                $accountData
            );
        }

        $this->command->info('✅ Cuentas contables básicas creadas exitosamente para BCommerce');
        $this->command->info('📊 Total de cuentas creadas: ' . count($accounts));
    }
}
