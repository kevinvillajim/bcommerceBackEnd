<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            // Hacer item_id nullable para permitir búsquedas
            $table->unsignedBigInteger('item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            // Revertir a not null
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
        });
    }
};
