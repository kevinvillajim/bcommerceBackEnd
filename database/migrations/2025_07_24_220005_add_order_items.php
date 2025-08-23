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
        Schema::table('order_items', function (Blueprint $table) {
            // Información del producto (para no depender de que exista en products)
            if (! Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->default('')->after('product_id');

            }

            if (! Schema::hasColumn('order_items', 'product_sku')) {
                $table->string('product_sku')->nullable()->after('product_name');
            }

            if (! Schema::hasColumn('order_items', 'product_image')) {
                $table->text('product_image')->nullable()->after('product_sku');
            }

            // Campos para descuentos por volumen
            if (! Schema::hasColumn('order_items', 'original_price')) {
                $table->decimal('original_price', 10, 2)->default(0)->after('price');
            }

            if (! Schema::hasColumn('order_items', 'volume_discount_percentage')) {
                $table->decimal('volume_discount_percentage', 5, 2)->default(0)->after('original_price');
            }

            if (! Schema::hasColumn('order_items', 'volume_savings')) {
                $table->decimal('volume_savings', 10, 2)->default(0)->after('volume_discount_percentage');
            }

            if (! Schema::hasColumn('order_items', 'discount_label')) {
                $table->string('discount_label')->nullable()->after('volume_savings');
            }

            // Campos adicionales del producto
            if (! Schema::hasColumn('order_items', 'seller_id')) {
                $table->foreignId('seller_id')->nullable()->after('product_image')->constrained('sellers')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $columns = [
                'product_name',
                'product_sku',
                'product_image',
                'original_price',
                'volume_discount_percentage',
                'volume_savings',
                'discount_label',
                'seller_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    if ($column === 'seller_id') {
                        $table->dropForeign(['seller_id']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
