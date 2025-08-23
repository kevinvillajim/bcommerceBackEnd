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
            // Verificar si el constraint ya existe usando raw SQL
            $indexExists = \DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = 'ratings' 
                AND index_name = 'unique_rating_with_product'
            ");

            if ($indexExists[0]->count == 0) {
                // Solo crear el índice si no existe
                $table->unique(['user_id', 'seller_id', 'order_id', 'product_id', 'type'], 'unique_rating_with_product');
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
