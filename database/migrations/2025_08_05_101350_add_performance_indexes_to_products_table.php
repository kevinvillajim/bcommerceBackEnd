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
        Schema::table('products', function (Blueprint $table) {
            // ✅ Índices para mejorar performance de admin products
            $table->index(['deleted_at', 'created_at'], 'products_deleted_created_idx');
            $table->index(['status', 'published'], 'products_status_published_idx');
            $table->index(['category_id', 'published'], 'products_category_published_idx');
            $table->index(['seller_id', 'status'], 'products_seller_status_idx');
            $table->index(['featured', 'published'], 'products_featured_published_idx');
            $table->index(['stock'], 'products_stock_idx');
            $table->index(['name'], 'products_name_search_idx');
            $table->index(['sku'], 'products_sku_search_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_deleted_created_idx');
            $table->dropIndex('products_status_published_idx');
            $table->dropIndex('products_category_published_idx');
            $table->dropIndex('products_seller_status_idx');
            $table->dropIndex('products_featured_published_idx');
            $table->dropIndex('products_stock_idx');
            $table->dropIndex('products_name_search_idx');
            $table->dropIndex('products_sku_search_idx');
        });
    }
};
