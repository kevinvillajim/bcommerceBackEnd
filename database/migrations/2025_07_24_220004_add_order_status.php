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
        Schema::table('orders', function (Blueprint $table) {
            // Agregar campos de fechas si no existen
            if (! Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('updated_at');
            }

            if (! Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('delivered_at');
            }

            // Índices para mejorar rendimiento en las consultas del comando
            $table->index(['status', 'delivered_at'], 'idx_orders_status_delivered');
            $table->index(['status', 'completed_at'], 'idx_orders_status_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex('idx_orders_status_delivered');
            $table->dropIndex('idx_orders_status_completed');

            // Eliminar columnas
            $table->dropColumn(['delivered_at', 'completed_at']);
        });
    }
};
