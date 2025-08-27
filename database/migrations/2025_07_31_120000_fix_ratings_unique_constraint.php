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
        // ✅ FIXED: Solo agregar el nuevo constraint si no existe
        // No intentar eliminar constraints que pueden no existir

        Schema::table('ratings', function (Blueprint $table) {
            // Verificar si el constraint ya existe de forma compatible con SQLite y MySQL
            try {
                // Intentar crear el índice, si ya existe fallará silenciosamente
                $table->unique(['user_id', 'seller_id', 'order_id', 'product_id', 'type'], 'unique_rating_with_product');
            } catch (\Illuminate\Database\QueryException $e) {
                // Si el índice ya existe, continúa silenciosamente
                if (!str_contains($e->getMessage(), 'Duplicate key name') && !str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            // Restaurar el constraint único original
            $table->dropUnique('unique_rating_with_product');
            $table->unique(['user_id', 'seller_id', 'order_id', 'type'], 'unique_rating');
        });
    }
};
