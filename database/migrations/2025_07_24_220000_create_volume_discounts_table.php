<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('volume_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('min_quantity'); // Cantidad mínima requerida
            $table->decimal('discount_percentage', 5, 2); // Porcentaje de descuento
            $table->string('label')->nullable(); // Etiqueta descriptiva
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Índices
            $table->index(['product_id', 'min_quantity']);
            $table->index('active');

            // Relación con productos
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // Constraint para evitar duplicados
            $table->unique(['product_id', 'min_quantity']);
        });

        // Insertar configuraciones para volume discounts
        DB::table('configurations')->insert([
            [
                'key' => 'volume_discounts.enabled',
                'value' => 'true',
                'description' => 'Habilitar descuentos por volumen en toda la tienda',
                'group' => 'volume_discounts',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'volume_discounts.stackable',
                'value' => 'false',
                'description' => 'Permitir que los descuentos por volumen se combinen con otros descuentos',
                'group' => 'volume_discounts',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'volume_discounts.default_tiers',
                'value' => '[{"quantity":3,"discount":5,"label":"Descuento 3+"},{"quantity":6,"discount":10,"label":"Descuento 6+"},{"quantity":12,"discount":15,"label":"Descuento 12+"}]',
                'description' => 'Niveles de descuento por defecto para nuevos productos',
                'group' => 'volume_discounts',
                'type' => 'json',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'volume_discounts.show_savings_message',
                'value' => 'true',
                'description' => 'Mostrar mensaje de ahorro en páginas de producto',
                'group' => 'volume_discounts',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volume_discounts');

        // Eliminar configuraciones
        DB::table('configurations')->where('group', 'volume_discounts')->delete();
    }
};
