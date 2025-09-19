<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Limpieza arquitectónica: Remover 'payment' del enum de admins
     * ya que ahora usamos el sistema independiente payment_users
     */
    public function up(): void
    {
        // PASO 1: Migrar cualquier admin con role='payment' al sistema independiente
        $paymentAdmins = DB::table('admins')->where('role', 'payment')->get();

        foreach ($paymentAdmins as $admin) {
            // Verificar si ya existe en payment_users
            $existsInPaymentUsers = DB::table('payment_users')
                ->where('user_id', $admin->user_id)
                ->exists();

            if (!$existsInPaymentUsers) {
                // Crear registro en payment_users
                DB::table('payment_users')->insert([
                    'user_id' => $admin->user_id,
                    'status' => 'active',
                    'permissions' => json_encode(['external_payments']),
                    'last_login_at' => $admin->last_login_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Cambiar el role a customer_support (default)
            DB::table('admins')
                ->where('id', $admin->id)
                ->update(['role' => 'customer_support']);
        }

        // PASO 2: Remover 'payment' del enum
        Schema::table('admins', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'content_manager', 'customer_support', 'analytics'])
                  ->default('customer_support')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar enum con 'payment'
        Schema::table('admins', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'content_manager', 'customer_support', 'analytics', 'payment'])
                  ->default('customer_support')
                  ->change();
        });
    }
};
