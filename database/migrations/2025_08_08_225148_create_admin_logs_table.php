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
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();

            // Nivel de criticidad del log
            $table->enum('level', ['error', 'critical', 'warning', 'info'])->default('error')->index();

            // Tipo de evento para categorización
            $table->string('event_type', 50)->index(); // 'api_error', 'database_error', 'payment_error', etc.

            // Mensaje principal del error
            $table->string('message', 255);

            // Contexto adicional en JSON (request, stack trace, etc.)
            $table->json('context')->nullable();

            // Información de la request
            $table->string('method', 10)->nullable(); // GET, POST, etc.
            $table->string('url', 500)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();

            // Usuario relacionado (si está autenticado)
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Código de respuesta HTTP
            $table->unsignedSmallInteger('status_code')->nullable()->index();

            // Hash para rate limiting (evitar duplicados)
            $table->string('error_hash', 32)->index(); // MD5 del error para deduplicación

            // Timestamp optimizado para limpieza
            $table->timestamp('created_at')->index();

            // Índices compuestos para queries eficientes
            $table->index(['level', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['error_hash', 'created_at']); // Para rate limiting

            // Clave foránea opcional (si el usuario existe)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
    }
};
