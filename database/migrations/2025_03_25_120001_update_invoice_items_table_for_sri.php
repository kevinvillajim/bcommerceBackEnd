<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // ✅ Cambiar nombre de campos antiguos a nuestros nombres correctos
            $table->renameColumn('description', 'product_name');
            $table->renameColumn('total', 'subtotal');
            $table->renameColumn('sri_product_code', 'product_code');
            
            // ✅ Hacer product_code NOT NULL (slug es único y siempre existe)
            $table->string('product_code')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // ✅ Revertir cambios de nombres
            $table->renameColumn('product_name', 'description');
            $table->renameColumn('subtotal', 'total');
            $table->renameColumn('product_code', 'sri_product_code');
            
            // ✅ Revertir product_code a nullable
            $table->string('sri_product_code')->nullable()->change();
        });
    }
};