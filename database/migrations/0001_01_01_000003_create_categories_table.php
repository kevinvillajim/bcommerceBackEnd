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
        // Verificar si la tabla categories ya existe
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('icon')->nullable();
                $table->string('image')->nullable();
                $table->integer('order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('featured')->default(false);
                $table->timestamps();

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('categories')
                    ->onDelete('set null');
            });
        } else {
            // Si la tabla ya existe, agregar campos faltantes
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'slug')) {
                    $table->string('slug')->unique()->after('name');
                }

                if (! Schema::hasColumn('categories', 'description')) {
                    $table->text('description')->nullable()->after('slug');
                }

                if (! Schema::hasColumn('categories', 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable()->after('description');

                    $table->foreign('parent_id')
                        ->references('id')
                        ->on('categories')
                        ->onDelete('set null');
                }

                if (! Schema::hasColumn('categories', 'icon')) {
                    $table->string('icon')->nullable()->after('parent_id');
                }

                if (! Schema::hasColumn('categories', 'image')) {
                    $table->string('image')->nullable()->after('icon');
                }

                if (! Schema::hasColumn('categories', 'order')) {
                    $table->integer('order')->default(0)->after('image');
                }

                if (! Schema::hasColumn('categories', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('order');
                }

                if (! Schema::hasColumn('categories', 'featured')) {
                    $table->boolean('featured')->default(false)->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hacemos nada en down para evitar pérdida de datos
    }
};
