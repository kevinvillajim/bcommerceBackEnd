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
        Schema::create('seller_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Datos de la tienda
            $table->string('store_name');
            $table->text('business_activity');
            $table->text('products_to_sell');

            // Datos personales y de contacto
            $table->string('ruc', 20);
            $table->string('contact_email');
            $table->string('phone');
            $table->text('physical_address');

            // Datos adicionales del formulario
            $table->text('business_description')->nullable();
            $table->text('experience')->nullable();
            $table->text('additional_info')->nullable();

            // Estado de la solicitud
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');

            $table->timestamps();

            // Índices
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_applications');
    }
};
