<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Contar órdenes que necesitan actualización antes de actualizarlas
        $countToUpdate = DB::table('seller_orders as so')
            ->join('orders as o', 'so.order_id', '=', 'o.id')
            ->where('so.payment_status', 'pending')
            ->where('o.payment_status', 'completed')
            ->where(function ($query) {
                $query->where('o.payment_method', 'datafast')
                    ->orWhereNull('o.payment_method');
            })
            ->count();

        // Verificar si estamos usando SQLite
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Para SQLite usar subconsulta en lugar de JOIN
            DB::statement("
                UPDATE seller_orders 
                SET 
                    payment_status = 'completed',
                    payment_method = COALESCE(
                        (SELECT payment_method FROM orders WHERE id = seller_orders.order_id), 
                        'datafast'
                    )
                WHERE 
                    payment_status = 'pending' 
                    AND order_id IN (
                        SELECT id FROM orders 
                        WHERE payment_status = 'completed' 
                        AND (payment_method = 'datafast' OR payment_method IS NULL)
                    )
            ");
        } else {
            // Para MySQL/PostgreSQL usar JOIN
            DB::statement("
                UPDATE seller_orders so
                INNER JOIN orders o ON so.order_id = o.id
                SET 
                    so.payment_status = 'completed',
                    so.payment_method = COALESCE(o.payment_method, 'datafast')
                WHERE 
                    so.payment_status = 'pending' 
                    AND o.payment_status = 'completed'
                    AND (o.payment_method = 'datafast' OR o.payment_method IS NULL)
            ");
        }

        // Log para información
        Log::info("Updated {$countToUpdate} Datafast seller orders to completed payment status");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a pending solo para órdenes de Datafast si es necesario
        DB::statement("
            UPDATE seller_orders 
            SET payment_status = 'pending'
            WHERE payment_method = 'datafast' AND payment_status = 'completed'
        ");
    }
};
