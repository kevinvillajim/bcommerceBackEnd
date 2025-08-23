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
        Schema::table('shippings', function (Blueprint $table) {
            // Agregar seller_order_id
            $table->foreignId('seller_order_id')->nullable()->after('order_id')->constrained('seller_orders')->onDelete('cascade');

            // Hacer order_id nullable temporalmente durante la migración
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shippings', function (Blueprint $table) {
            $table->dropForeign(['seller_order_id']);
            $table->dropColumn('seller_order_id');

            // Restaurar order_id como requerido
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });
    }
};
