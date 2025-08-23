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
        Schema::create('admin_discount_codes', function (Blueprint $table) {
            $table->id();

            // Código único del descuento
            $table->string('code', 50)->unique();

            // Porcentaje de descuento (5-50%)
            $table->unsignedTinyInteger('discount_percentage')->default(10);

            // Información de uso
            $table->boolean('is_used')->default(false);
            $table->unsignedBigInteger('used_by')->nullable(); // ID del usuario que lo usó
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_on_product_id')->nullable(); // ID del producto donde se usó

            // Fecha de expiración
            $table->timestamp('expires_at')->nullable();

            // Descripción o notas del admin (opcional)
            $table->text('description')->nullable();

            // Admin que lo creó
            $table->unsignedBigInteger('created_by'); // ID del admin

            $table->timestamps();

            // Índices
            $table->index(['code']);
            $table->index(['is_used']);
            $table->index(['expires_at']);
            $table->index(['created_by']);
            $table->index(['used_by']);

            // Claves foráneas
            $table->foreign('used_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('used_on_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_discount_codes');
    }
};
