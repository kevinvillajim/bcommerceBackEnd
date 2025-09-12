<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ✅ Actualizar enum de estados para incluir nuevos estados de la API SRI v2
            $table->dropColumn('status');
            $table->enum('status', [
                'DRAFT', 
                'SENT_TO_SRI', 
                'AUTHORIZED', 
                'REJECTED', 
                'FAILED', 
                'DEFINITIVELY_FAILED',
                'PENDING',           // ✅ Estado inicial de SRI
                'PROCESSING',        // ✅ API está procesando
                'RECEIVED',          // ✅ SRI recibió la factura
                'RETURNED',          // ✅ Devuelta por SRI
                'NOT_AUTHORIZED',    // ✅ No autorizada por SRI
                'SRI_ERROR'          // ✅ Error en SRI
            ])->default('DRAFT')->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ✅ Revertir a estados anteriores
            $table->dropColumn('status');
            $table->enum('status', [
                'DRAFT', 
                'SENT_TO_SRI', 
                'AUTHORIZED', 
                'REJECTED', 
                'FAILED', 
                'DEFINITIVELY_FAILED'
            ])->default('DRAFT')->after('total_amount');
        });
    }
};
