<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('interaction_type');
            $table->unsignedBigInteger('item_id')->nullable(); // ✅ NULLABLE para búsquedas
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'interaction_type']);
            $table->index(['item_id', 'interaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
    }
};
