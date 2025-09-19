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
        Schema::table('credit_notes', function (Blueprint $table) {
            // Hacer invoice_id nullable para permitir notas de crédito sin factura específica
            $table->foreignId('invoice_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            // Revertir: hacer invoice_id requerido nuevamente
            $table->foreignId('invoice_id')->nullable(false)->change();
        });
    }
};
