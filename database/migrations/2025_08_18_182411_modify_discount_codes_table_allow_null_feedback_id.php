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
        Schema::table('discount_codes', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['feedback_id']);
            
            // Modify the column to allow null values
            $table->foreignId('feedback_id')->nullable()->change();
            
            // Re-add the foreign key constraint that allows null
            $table->foreign('feedback_id')->references('id')->on('feedback')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['feedback_id']);
            
            // Revert to not nullable
            $table->foreignId('feedback_id')->change();
            
            // Re-add the original foreign key constraint
            $table->foreign('feedback_id')->references('id')->on('feedback')->onDelete('cascade');
        });
    }
};
