<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('billing_data')->nullable()->after('shipping_data')->comment('Datos de facturación separados del envío para SRI');
        });

        // ✅ LOG: Confirmación de migration exitosa
        Log::info('🔧 MIGRATION: billing_data field created', [
            'table' => 'orders',
            'field' => 'billing_data',
            'type' => 'json',
            'nullable' => true,
            'position' => 'after shipping_data',
            'purpose' => 'SRI billing data separation'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('billing_data');
        });

        Log::info('🔄 MIGRATION ROLLBACK: billing_data field dropped', [
            'table' => 'orders',
            'field' => 'billing_data'
        ]);
    }
};
