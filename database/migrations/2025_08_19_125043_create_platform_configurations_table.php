<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('description')->nullable();
            $table->string('category')->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insertar configuraciones por defecto
        DB::table('platform_configurations')->insert([
            [
                'key' => 'platform.commission_rate',
                'value' => json_encode(10.0),
                'description' => 'Porcentaje de comisión que cobra la plataforma a los vendedores',
                'category' => 'finance',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'shipping.single_seller_percentage',
                'value' => json_encode(80.0),
                'description' => 'Porcentaje del costo de envío que recibe un seller cuando es el único en la orden',
                'category' => 'shipping',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'shipping.multiple_sellers_percentage',
                'value' => json_encode(40.0),
                'description' => 'Porcentaje del costo de envío que recibe cada seller cuando hay múltiples en la orden',
                'category' => 'shipping',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_configurations');
    }
};
