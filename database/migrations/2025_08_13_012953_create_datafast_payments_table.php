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
        Schema::create('datafast_payments', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            // Identificadores únicos de Datafast
            $table->string('transaction_id', 100)->unique(); // Nuestro ID interno
            $table->string('checkout_id', 100)->nullable(); // ID del checkout de Datafast
            $table->string('datafast_payment_id', 100)->nullable(); // ID de pago de Datafast
            $table->string('resource_path', 255)->nullable(); // Path del recurso de verificación

            // Información financiera
            $table->decimal('amount', 10, 2); // Monto total
            $table->decimal('calculated_total', 10, 2)->nullable(); // Total calculado del frontend
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->decimal('tax', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            // Estados del pago
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('payment_status', 50)->nullable(); // Estado específico de Datafast
            $table->string('result_code', 20)->nullable(); // Código de resultado de Datafast
            $table->text('result_description')->nullable(); // Descripción del resultado

            // Información del cliente
            $table->string('customer_given_name', 50)->nullable();
            $table->string('customer_middle_name', 50)->nullable();
            $table->string('customer_surname', 50)->nullable();
            $table->string('customer_phone', 25)->nullable();
            $table->string('customer_doc_id', 15)->nullable();
            $table->string('customer_email', 100)->nullable();

            // Información de envío
            $table->string('shipping_address', 200)->nullable();
            $table->string('shipping_city', 50)->nullable();
            $table->string('shipping_country', 2)->nullable();

            // Información técnica
            $table->string('environment', 20)->default('test'); // test, production
            $table->string('phase', 10)->default('phase2'); // phase1, phase2
            $table->string('widget_url', 500)->nullable();
            $table->ipAddress('client_ip')->nullable();
            $table->text('user_agent')->nullable();

            // Datos de descuentos
            $table->string('discount_code', 50)->nullable();
            $table->json('discount_info')->nullable(); // Información completa del descuento aplicado

            // Logs y debugging
            $table->json('request_data')->nullable(); // Datos enviados a Datafast
            $table->json('response_data')->nullable(); // Respuesta de Datafast
            $table->json('verification_data')->nullable(); // Datos de verificación
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable(); // Notas adicionales

            // Timestamps específicos
            $table->timestamp('checkout_created_at')->nullable();
            $table->timestamp('payment_attempted_at')->nullable();
            $table->timestamp('payment_completed_at')->nullable();
            $table->timestamp('verification_completed_at')->nullable();

            $table->timestamps();

            // Índices para optimización
            $table->index(['user_id', 'status']);
            $table->index(['checkout_id']);
            $table->index(['datafast_payment_id']);
            $table->index(['status', 'created_at']);
            $table->index(['environment', 'phase']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datafast_payments');
    }
};
