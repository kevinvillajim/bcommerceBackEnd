<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla credit_notes siguiendo el patrón exitoso de invoices
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();

            // ✅ Numeración y relaciones
            $table->string('credit_note_number')->unique();
            $table->foreignId('invoice_id')->constrained('invoices'); // Factura original que se modifica
            $table->foreignId('order_id')->nullable()->constrained('orders'); // Orden original (si existe)
            $table->foreignId('user_id')->constrained('users'); // Usuario que creó la nota
            $table->foreignId('transaction_id')->nullable()->constrained('accounting_transactions');

            // ✅ Fechas y documento modificado
            $table->datetime('issue_date');
            $table->string('motivo'); // Motivo de la nota de crédito
            $table->string('documento_modificado_tipo', 2)->default('01'); // 01=Factura
            $table->string('documento_modificado_numero'); // 001-001-000000123
            $table->date('documento_modificado_fecha'); // Fecha del documento original

            // ✅ Totales financieros
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 10)->default('DOLAR');

            // ✅ Estados SRI (idénticos a invoices)
            $table->enum('status', [
                'DRAFT',
                'SENT_TO_SRI',
                'PENDING',           // Estado inicial de SRI
                'PROCESSING',        // API está procesando
                'RECEIVED',          // SRI recibió la nota
                'AUTHORIZED',        // Autorizada por SRI
                'REJECTED',          // Rechazada
                'NOT_AUTHORIZED',    // No autorizada por SRI
                'RETURNED',          // Devuelta por SRI
                'SRI_ERROR',         // Error en SRI
                'FAILED',            // Fallida
                'DEFINITIVELY_FAILED', // Fallida definitivamente
            ])->default('DRAFT');

            // ✅ Datos del cliente (mismos que factura original)
            $table->string('customer_identification');
            $table->string('customer_identification_type', 2); // "05" Cédula o "04" RUC
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->text('customer_address');
            $table->string('customer_phone')->nullable();

            // ✅ SRI Response y autorización
            $table->string('sri_access_key', 100)->nullable(); // Clave de acceso de 49 dígitos
            $table->string('sri_authorization_number')->nullable();
            $table->json('sri_response')->nullable(); // Respuesta completa del SRI
            $table->string('sri_error_message')->nullable();

            // ✅ Sistema de reintentos (igual que facturas)
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();

            // ✅ Metadatos
            $table->string('created_via', 20)->default('manual'); // manual, system
            $table->string('pdf_path')->nullable(); // Ruta al PDF generado

            $table->timestamps();

            // ✅ Índices para optimización
            $table->index(['status']);
            $table->index(['invoice_id']);
            $table->index(['sri_access_key']);
            $table->index(['issue_date']);
            $table->index(['customer_identification']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};