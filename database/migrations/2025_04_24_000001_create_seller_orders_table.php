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
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('restrict');
            $table->decimal('total', 10, 2);
            $table->string('status')->default('pending');
            $table->json('shipping_data')->nullable();
            $table->string('order_number')->nullable();
            $table->timestamps();
        });

        // Modificar la tabla order_items para agregar la referencia a seller_order
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('seller_order_id')->nullable()->after('order_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['seller_order_id']);
            $table->dropColumn('seller_order_id');
        });

        Schema::dropIfExists('seller_orders');
    }
};
