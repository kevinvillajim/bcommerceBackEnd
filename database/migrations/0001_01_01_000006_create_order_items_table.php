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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->integer('quantity');
            $table->decimal('price', 10, 2)->comment('Precio final con descuentos aplicados');

            // ✅ CONSOLIDADO: Volume discount fields
            $table->decimal('original_price', 10, 2)->nullable()->comment('Precio original antes de descuentos');
            $table->decimal('volume_discount_percentage', 5, 2)->default(0)->comment('Porcentaje de descuento por volumen aplicado');
            $table->decimal('volume_savings', 10, 2)->default(0)->comment('Ahorro total por descuento de volumen para este item');
            $table->string('discount_label')->nullable()->comment('Etiqueta descriptiva del descuento aplicado');

            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            // ✅ MySQL: Índices para optimización
            $table->index('volume_discount_percentage');
            $table->index(['order_id', 'product_id']);
            $table->index('quantity'); // Para reportes de volumen
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
