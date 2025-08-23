<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar payment_status basado en el estado de la orden principal
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a valores por defecto
        DB::table('seller_orders')->update([
            'payment_status' => 'pending',
            'payment_method' => null
        ]);
    }
};
