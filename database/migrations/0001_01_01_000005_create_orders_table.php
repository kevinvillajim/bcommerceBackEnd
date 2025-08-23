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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->onDelete('set null');
            $table->foreignId('seller_order_id')->nullable();

            // ✅ CONSOLIDADO: Campos de pricing básicos
            $table->decimal('total', 10, 2);
            $table->decimal('original_total', 10, 2)->nullable()->comment('Total original antes de descuentos');
            $table->decimal('subtotal_products', 10, 2)->default(0)->comment('Subtotal de productos');
            $table->decimal('iva_amount', 10, 2)->default(0)->comment('Monto del IVA');
            $table->decimal('shipping_cost', 10, 2)->default(0)->comment('Costo de envío');
            $table->decimal('total_discounts', 10, 2)->default(0)->comment('Total de descuentos aplicados');

            // ✅ CONSOLIDADO: Volume discounts
            $table->decimal('volume_discount_savings', 10, 2)->default(0)->comment('Ahorros por descuentos de volumen');
            $table->decimal('seller_discount_savings', 10, 2)->default(0)->comment('Ahorros por descuentos del seller');
            $table->boolean('volume_discounts_applied')->default(false)->comment('Si se aplicaron descuentos por volumen');

            // ✅ CONSOLIDADO: Shipping fields
            $table->boolean('free_shipping')->default(false)->comment('Si el envío es gratis');
            $table->decimal('free_shipping_threshold', 10, 2)->nullable()->comment('Umbral para envío gratis aplicado');
            $table->json('pricing_breakdown')->nullable()->comment('Desglose detallado de precios');

            // ✅ CONSOLIDADO: Discount codes
            $table->string('feedback_discount_code', 20)->nullable()->comment('Código de descuento de feedback aplicado');
            $table->decimal('feedback_discount_amount', 10, 2)->default(0)->comment('Monto del descuento de feedback');
            $table->decimal('feedback_discount_percentage', 5, 2)->default(0)->comment('Porcentaje del descuento de feedback');

            // Status y payment fields
            $table->string('status')->default('pending');
            $table->string('payment_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->json('payment_details')->nullable();
            $table->json('shipping_data')->nullable();
            $table->string('order_number')->unique();
            $table->timestamps();

            // ✅ MySQL: Índices para optimización
            $table->index('status');
            $table->index('payment_status');
            $table->index('volume_discounts_applied');
            $table->index('free_shipping');
            $table->index(['user_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('order_number'); // Ya es unique pero agregar índice explícito
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
