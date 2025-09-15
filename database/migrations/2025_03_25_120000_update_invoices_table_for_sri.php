<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ✅ Eliminar campos antiguos que no usamos
            $table->dropForeign(['seller_id']);
            $table->dropColumn('seller_id');
            $table->dropColumn('cancellation_reason');
            $table->dropColumn('cancelled_at');

            // ✅ Cambiar tipo de issue_date a datetime
            $table->datetime('issue_date')->change();

            // ✅ Actualizar estados SRI correctos
            $table->dropColumn('status');
            $table->enum('status', [
                'DRAFT',
                'SENT_TO_SRI',
                'AUTHORIZED',
                'REJECTED',
                'FAILED',
                'DEFINITIVELY_FAILED',
            ])->default('DRAFT')->after('total_amount');

            // ✅ Agregar campos de cliente SRI
            $table->string('customer_identification')->after('total_amount');
            $table->string('customer_identification_type', 2)->after('customer_identification'); // "05" o "04"
            $table->string('customer_name')->after('customer_identification_type');
            $table->string('customer_email')->after('customer_name');
            $table->text('customer_address')->after('customer_email');
            $table->string('customer_phone')->after('customer_address');

            // ✅ Agregar campo currency
            $table->string('currency', 10)->default('DOLAR')->after('total_amount');

            // ✅ Agregar campos de reintentos
            $table->integer('retry_count')->default(0)->after('sri_response');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            $table->string('sri_error_message')->nullable()->after('last_retry_at');

            // ✅ Agregar campo created_via
            $table->string('created_via', 20)->default('checkout')->after('sri_error_message'); // checkout, manual
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ✅ Revertir cambios
            $table->dropColumn([
                'customer_identification',
                'customer_identification_type',
                'customer_name',
                'customer_email',
                'customer_address',
                'customer_phone',
                'currency',
                'retry_count',
                'last_retry_at',
                'sri_error_message',
                'created_via',
            ]);

            // ✅ Restaurar seller_id
            $table->foreignId('seller_id')->constrained('sellers')->after('user_id');

            // ✅ Restaurar campos antiguos
            $table->string('cancellation_reason')->nullable()->after('sri_response');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');

            // ✅ Restaurar estados antiguos
            $table->dropColumn('status');
            $table->enum('status', ['DRAFT', 'ISSUED', 'CANCELLED', 'ERROR'])->default('DRAFT')->after('total_amount');

            // ✅ Cambiar issue_date de vuelta a date
            $table->date('issue_date')->change();
        });
    }
};
