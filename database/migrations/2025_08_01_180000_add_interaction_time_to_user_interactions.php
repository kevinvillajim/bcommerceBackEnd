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
        Schema::table('user_interactions', function (Blueprint $table) {
            // Hacer item_id nullable
            $table->unsignedBigInteger('item_id')->nullable()->change();

            // Agregar interaction_time
            $table->timestamp('interaction_time')->nullable()->after('metadata');
            $table->index('interaction_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            $table->dropIndex(['interaction_time']);
            $table->dropColumn('interaction_time');

            // Revertir item_id a no nullable
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
        });
    }
};
