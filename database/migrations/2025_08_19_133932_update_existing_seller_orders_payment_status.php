<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar si estamos usando SQLite
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Para SQLite usar subconsulta en lugar de JOIN
            DB::statement("
                UPDATE seller_orders 
                SET 
                    payment_status = CASE 
                        WHEN (SELECT payment_status FROM orders WHERE id = seller_orders.order_id) = 'completed' THEN 'completed'
                        ELSE 'pending'
                    END,
                    payment_method = COALESCE(
                        (SELECT payment_method FROM orders WHERE id = seller_orders.order_id), 
                        'datafast'
                    )
                WHERE payment_status = 'pending' OR payment_status IS NULL
            ");
        } else {
            // Para MySQL/PostgreSQL usar JOIN
            DB::statement("
                UPDATE seller_orders so
                INNER JOIN orders o ON so.order_id = o.id
                SET 
                    so.payment_status = CASE 
                        WHEN o.payment_status = 'completed' THEN 'completed'
                        ELSE 'pending'
                    END,
                    so.payment_method = COALESCE(o.payment_method, 'datafast')
                WHERE so.payment_status = 'pending' OR so.payment_status IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a valores por defecto
        DB::table('seller_orders')->update([
            'payment_status' => 'pending',
            'payment_method' => null,
        ]);
    }
};
