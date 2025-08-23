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
        // Tabla orders
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'original_total')) {
                $table->decimal('original_total', 10, 2)->nullable()->after('total')
                    ->comment('Total original antes de aplicar descuentos por volumen');
            }

            if (! Schema::hasColumn('orders', 'volume_discount_savings')) {
                $table->decimal('volume_discount_savings', 10, 2)->default(0)->after('original_total')
                    ->comment('Total ahorrado por descuentos por volumen');
            }

            if (! Schema::hasColumn('orders', 'volume_discounts_applied')) {
                $table->boolean('volume_discounts_applied')->default(false)->after('volume_discount_savings')
                    ->comment('Indica si se aplicaron descuentos por volumen');
            }

            if (! Schema::hasColumn('orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 8, 2)->default(0)->after('volume_discounts_applied')
                    ->comment('Costo de envío');
            }

            if (! Schema::hasColumn('orders', 'free_shipping')) {
                $table->boolean('free_shipping')->default(false)->after('shipping_cost')
                    ->comment('Indica si el envío es gratis');
            }

            if (! Schema::hasColumn('orders', 'free_shipping_threshold')) {
                $table->decimal('free_shipping_threshold', 8, 2)->nullable()->after('free_shipping')
                    ->comment('Umbral para envío gratis que se aplicó');
            }
        });

        // Índices para orders
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS orders_volume_discounts_applied_index ON orders (volume_discounts_applied)');
        } catch (\Throwable $e) {
            logger()->warning("Índice 'orders_volume_discounts_applied_index' no creado: ".$e->getMessage());
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS orders_free_shipping_index ON orders (free_shipping)');
        } catch (\Throwable $e) {
            logger()->warning("Índice 'orders_free_shipping_index' no creado: ".$e->getMessage());
        }

        // Tabla seller_orders
        Schema::table('seller_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('seller_orders', 'original_total')) {
                $table->decimal('original_total', 10, 2)->nullable()->after('total')
                    ->comment('Total original antes de aplicar descuentos por volumen');
            }

            if (! Schema::hasColumn('seller_orders', 'volume_discount_savings')) {
                $table->decimal('volume_discount_savings', 10, 2)->default(0)->after('original_total')
                    ->comment('Total ahorrado por descuentos por volumen');
            }

            if (! Schema::hasColumn('seller_orders', 'volume_discounts_applied')) {
                $table->boolean('volume_discounts_applied')->default(false)->after('volume_discount_savings')
                    ->comment('Indica si se aplicaron descuentos por volumen');
            }

            if (! Schema::hasColumn('seller_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 8, 2)->default(0)->after('volume_discounts_applied')
                    ->comment('Costo de envío para este vendedor');
            }

            try {
                DB::statement('CREATE INDEX IF NOT EXISTS seller_orders_volume_discounts_applied_index ON seller_orders (volume_discounts_applied)');
            } catch (\Throwable $e) {
                logger()->warning("Índice 'seller_orders_volume_discounts_applied_index' no creado: ".$e->getMessage());
            }
        });

        // ✅ CONSOLIDADO: Campos de order_items ya integrados en create_order_items_table.php
        // Los campos de volume discounts se manejan directamente en la tabla principal

        // Configuraciones de envío
        if (Schema::hasTable('configurations')) {
            $keys = DB::table('configurations')->whereIn('key', [
                'shipping.free_threshold',
                'shipping.default_cost',
                'shipping.enabled',
            ])->pluck('key')->toArray();

            $configToInsert = [];

            if (! in_array('shipping.free_threshold', $keys)) {
                $configToInsert[] = [
                    'key' => 'shipping.free_threshold',
                    'value' => '50.00',
                    'description' => 'Umbral en USD para envío gratis',
                    'group' => 'shipping',
                    'type' => 'decimal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! in_array('shipping.default_cost', $keys)) {
                $configToInsert[] = [
                    'key' => 'shipping.default_cost',
                    'value' => '5.00',
                    'description' => 'Costo de envío por defecto en USD',
                    'group' => 'shipping',
                    'type' => 'decimal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! in_array('shipping.enabled', $keys)) {
                $configToInsert[] = [
                    'key' => 'shipping.enabled',
                    'value' => 'true',
                    'description' => 'Habilitar cálculo de costos de envío',
                    'group' => 'shipping',
                    'type' => 'boolean',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($configToInsert)) {
                DB::table('configurations')->insert($configToInsert);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'original_price',
                'volume_discount_percentage',
                'volume_savings',
                'discount_label',
            ]);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'original_total',
                'volume_discount_savings',
                'volume_discounts_applied',
                'shipping_cost',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'original_total',
                'volume_discount_savings',
                'volume_discounts_applied',
                'shipping_cost',
                'free_shipping',
                'free_shipping_threshold',
            ]);
        });

        if (Schema::hasTable('configurations')) {
            DB::table('configurations')
                ->whereIn('key', [
                    'shipping.free_threshold',
                    'shipping.default_cost',
                    'shipping.enabled',
                ])->delete();
        }
    }
};
