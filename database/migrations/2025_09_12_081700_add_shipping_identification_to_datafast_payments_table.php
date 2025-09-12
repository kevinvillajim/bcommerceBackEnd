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
        Schema::table('datafast_payments', function (Blueprint $table) {
            $table->string('shipping_identification', 15)->nullable()->after('shipping_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datafast_payments', function (Blueprint $table) {
            $table->dropColumn('shipping_identification');
        });
    }
};
