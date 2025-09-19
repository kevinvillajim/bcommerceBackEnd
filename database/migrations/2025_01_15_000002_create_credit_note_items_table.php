<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla credit_note_items siguiendo el patrón de invoice_items
     */
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();

            // ✅ Relación con nota de crédito
            $table->foreignId('credit_note_id')->constrained('credit_notes')->onDelete('cascade');

            // ✅ Producto (puede ser null si es item manual)
            $table->foreignId('product_id')->nullable()->constrained('products');

            // ✅ Información del producto/servicio (campos actualizados como invoice_items)
            $table->string('product_code'); // Código interno del producto/servicio
            $table->string('product_name'); // Descripción del producto/servicio
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);

            // ✅ Información fiscal
            $table->decimal('tax_rate', 5, 2); // Porcentaje de IVA (15%, 12%, 0%, etc.)
            $table->decimal('tax_amount', 12, 2); // Monto del IVA calculado
            $table->decimal('subtotal', 12, 2); // Subtotal del item

            // ✅ Código de IVA SRI (importante para Ecuador)
            $table->string('codigo_iva', 1)->default('2'); // 0=0%, 2=12%, 3=14%, 4=15%, 6=No objeto, 7=Exento

            $table->timestamps();

            // ✅ Índices para optimización
            $table->index(['credit_note_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};