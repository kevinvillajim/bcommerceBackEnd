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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // ID del vendedor/usuario que publica el producto
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->onDelete('set null'); // ✅ FIXED: Removed after()
            $table->unsignedBigInteger('category_id');
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->longText('description'); // ✅ MySQL: longText para descripciones extensas
            $table->longText('short_description')->nullable(); // ✅ MOVED: From after() position
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);

            // Atributos físicos
            $table->float('weight')->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('depth', 8, 2)->nullable();
            $table->string('dimensions')->nullable(); // Formato alternativo como "10x20x30"

            // Variantes y opciones
            $table->json('colors')->nullable();
            $table->json('sizes')->nullable();
            $table->json('tags')->nullable();

            // Identificación y metadatos
            $table->string('sku')->nullable()->unique();
            $table->json('attributes')->nullable(); // Atributos adicionales
            $table->json('images')->nullable(); // URLs de las imágenes

            // Estados y flags
            $table->boolean('featured')->default(false);
            $table->boolean('published')->default(true);
            $table->string('status')->default('active');

            // Estadísticas y métricas
            $table->integer('view_count')->default(0);
            $table->integer('sales_count')->default(0); // Contador de ventas
            $table->decimal('rating', 3, 1)->default(0); // ✅ MOVED: From after() position
            $table->integer('rating_count')->default(0); // ✅ MOVED: From after() position
            $table->decimal('discount_percentage', 5, 2)->default(0);

            // Fechas de disponibilidad - ✅ MOVED: From after() positions
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();

            // Información de producto - ✅ MOVED: All after() removed
            $table->string('brand')->nullable();
            $table->string('warranty_info')->nullable();
            $table->integer('warranty_duration')->nullable();

            // Información de envío - ✅ MOVED: All after() removed
            $table->string('shipping_info')->nullable();
            $table->json('shipping_dimensions')->nullable();
            $table->decimal('shipping_weight', 8, 2)->nullable();

            // SEO - ✅ MOVED: All after() removed
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();

            // Multimedia - ✅ MOVED: All after() removed
            $table->string('video_url')->nullable();

            // Características adicionales - ✅ MOVED: All after() removed
            $table->boolean('assembly_required')->default(false);
            $table->longText('assembly_instructions')->nullable(); // ✅ MySQL: longText
            $table->string('packaging_info')->nullable();
            $table->string('external_url')->nullable();

            // Información técnica - ✅ MOVED: All after() removed
            $table->string('model_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('country_of_origin')->nullable();

            // Restricciones de compra - ✅ MOVED: All after() removed
            $table->integer('minimum_purchase')->default(1);
            $table->integer('maximum_purchase')->nullable();

            // Información fiscal - ✅ MOVED: All after() removed
            $table->boolean('is_taxable')->default(true);
            $table->string('tax_class')->nullable();

            // Códigos de identificación - ✅ MOVED: All after() removed
            $table->string('barcode')->nullable();
            $table->string('mpn')->nullable(); // Manufacturer Part Number
            $table->string('gtin')->nullable(); // Global Trade Item Number
            $table->string('upc')->nullable(); // Universal Product Code
            $table->string('ean')->nullable(); // European Article Number
            $table->string('isbn')->nullable(); // International Standard Book Number

            // Productos digitales - ✅ MOVED: All after() removed
            $table->boolean('downloadable')->default(false);
            $table->string('download_link')->nullable();
            $table->integer('download_expiry')->nullable(); // Días
            $table->integer('download_limit')->nullable(); // Número de descargas

            // Soporte para variaciones de producto - ✅ MOVED: All after() removed
            $table->unsignedBigInteger('variation_parent_id')->nullable();
            $table->boolean('is_variation')->default(false);
            $table->json('variation_attributes')->nullable();

            // Timestamps y soft deletes
            $table->timestamps();
            $table->softDeletes(); // Para no eliminar productos que ya tienen órdenes

            // ✅ MYSQL: Índices básicos
            $table->index('user_id');
            $table->index('category_id');
            $table->index('price');
            $table->index('status');
            $table->index('featured');
            $table->index('published');

            // ✅ MYSQL: Índices adicionales para rendimiento
            $table->index('brand');
            $table->index('rating');
            $table->index('available_from');
            $table->index('available_until');
            $table->index('downloadable');
            $table->index('variation_parent_id');

            // Restricción de clave foránea para variaciones
            $table->foreign('variation_parent_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            // Relaciones
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::table('products', function (Blueprint $table) {});
    }
};
