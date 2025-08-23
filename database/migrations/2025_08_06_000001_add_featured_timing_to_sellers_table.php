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
        Schema::table('sellers', function (Blueprint $table) {
            $table->timestamp('featured_at')->nullable()->after('is_featured');
            $table->timestamp('featured_expires_at')->nullable()->after('featured_at');
            $table->string('featured_reason')->nullable()->after('featured_expires_at'); // 'admin', 'feedback', etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['featured_at', 'featured_expires_at', 'featured_reason']);
        });
    }
};
