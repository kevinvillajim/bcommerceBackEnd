<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            ->where(function($query) {
                $query->where('o.payment_method', 'datafast')
                      ->orWhereNull('o.payment_method');
            })
            ->count();

        // Actualizar seller_orders existentes para órdenes de Datafast
        // que fueron pagadas exitosamente pero tienen payment_status = 'pending'
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
