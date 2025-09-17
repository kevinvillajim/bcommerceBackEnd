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
        // 1. Extender enum de roles en tabla admins para incluir 'payment'
        Schema::table('admins', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'content_manager', 'customer_support', 'analytics', 'payment'])
                  ->default('customer_support')
                  ->change();
        });

        // 2. Crear tabla independiente para links de pagos externos
        Schema::create('external_payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('link_code', 32)->unique(); // Código único para URL pública
            $table->string('customer_name', 255); // Nombre del cliente que va a pagar
            $table->decimal('amount', 10, 2); // Monto a pagar
            $table->text('description')->nullable(); // Descripción opcional del pago
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled'])->default('pending');
            $table->string('payment_method', 50)->nullable(); // 'datafast' o 'deuna' una vez pagado
            $table->string('transaction_id', 255)->nullable(); // ID de transacción del proveedor
            $table->string('payment_id', 255)->nullable(); // ID del pago del proveedor
            $table->timestamp('expires_at'); // Fecha de expiración del link
            $table->timestamp('paid_at')->nullable(); // Fecha cuando se completó el pago
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Usuario que creó el link
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index('link_code');
            $table->index('status');
            $table->index('created_by');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar tabla de links de pagos externos
        Schema::dropIfExists('external_payment_links');

        // Revertir enum de roles (quitar 'payment')
        Schema::table('admins', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'content_manager', 'customer_support', 'analytics'])
                  ->default('customer_support')
                  ->change();
        });
    }
};
