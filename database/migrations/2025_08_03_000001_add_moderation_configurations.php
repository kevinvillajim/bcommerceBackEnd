<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar configuraciones de moderación
        $moderationConfigs = [
            [
                'key' => 'moderation.userStrikesThreshold',
                'value' => '3',
                'description' => 'Número de strikes que puede acumular un usuario antes de ser bloqueado automáticamente',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.contactScorePenalty',
                'value' => '3',
                'description' => 'Puntos añadidos al score de contacto por patrones sospechosos',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.businessScoreBonus',
                'value' => '15',
                'description' => 'Puntos añadidos al score de negocio cuando se detectan términos comerciales legítimos',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.contactPenaltyHeavy',
                'value' => '20',
                'description' => 'Penalización severa aplicada cuando se detectan indicadores claros de contacto',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.minimumContactScore',
                'value' => '8',
                'description' => 'Score mínimo para considerar que un mensaje contiene información de contacto',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.scoreDifferenceThreshold',
                'value' => '5',
                'description' => 'Diferencia mínima entre score de contacto y negocio para activar moderación',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.consecutiveNumbersLimit',
                'value' => '7',
                'description' => 'Número de dígitos consecutivos que activan la detección de teléfonos',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.numbersWithContextLimit',
                'value' => '3',
                'description' => 'Número de dígitos que, junto con palabras de contacto, activan la detección',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'moderation.lowStockThreshold',
                'value' => '5',
                'description' => 'Cantidad mínima de productos en stock antes de mostrar aviso de stock bajo',
                'group' => 'moderation',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Agregar configuraciones de seguridad adicionales
        $securityConfigs = [
            [
                'key' => 'security.passwordMinLength',
                'value' => '8',
                'description' => 'Longitud mínima requerida para contraseñas de usuario',
                'group' => 'security',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.passwordRequireSpecial',
                'value' => 'true',
                'description' => 'Requerir al menos un carácter especial en las contraseñas',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.passwordRequireUppercase',
                'value' => 'true',
                'description' => 'Requerir al menos una letra mayúscula en las contraseñas',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.passwordRequireNumbers',
                'value' => 'true',
                'description' => 'Requerir al menos un número en las contraseñas',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.accountLockAttempts',
                'value' => '5',
                'description' => 'Número de intentos de login fallidos antes de bloquear temporalmente la cuenta',
                'group' => 'security',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.sessionTimeout',
                'value' => '120',
                'description' => 'Tiempo de inactividad en minutos antes de cerrar sesión automáticamente',
                'group' => 'security',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.enableTwoFactor',
                'value' => 'false',
                'description' => 'Habilitar autenticación de dos factores para todos los usuarios',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.requireEmailVerification',
                'value' => 'true',
                'description' => 'Requerir verificación de email para cuentas nuevas',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.adminIpRestriction',
                'value' => '',
                'description' => 'Lista de IPs permitidas para acceso administrativo (una por línea, vacío = sin restricción)',
                'group' => 'security',
                'type' => 'textarea',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.enableCaptcha',
                'value' => 'false',
                'description' => 'Habilitar CAPTCHA en formularios de registro y login',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Agregar configuraciones de límites del sistema
        $limitsConfigs = [
            [
                'key' => 'limits.cartMaxItems',
                'value' => '100',
                'description' => 'Número máximo de productos diferentes que puede tener un carrito',
                'group' => 'limits',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'limits.cartMaxQuantityPerItem',
                'value' => '99',
                'description' => 'Cantidad máxima que se puede agregar de un mismo producto',
                'group' => 'limits',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'limits.orderTimeout',
                'value' => '15',
                'description' => 'Tiempo límite en minutos para completar el proceso de pago',
                'group' => 'limits',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'limits.recommendationLimit',
                'value' => '10',
                'description' => 'Número de productos recomendados mostrados por defecto',
                'group' => 'limits',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'limits.maxRecommendationResults',
                'value' => '10000',
                'description' => 'Límite máximo de resultados que puede procesar el sistema de recomendaciones',
                'group' => 'limits',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'limits.tokenRefreshThreshold',
                'value' => '15',
                'description' => 'Minutos antes de expiración para renovar tokens automáticamente',
                'group' => 'limits',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insertar todas las configuraciones (ignorar duplicados)
        foreach (array_merge($moderationConfigs, $securityConfigs, $limitsConfigs) as $config) {
            DB::table('configurations')->updateOrInsert(
                ['key' => $config['key']],
                $config
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar configuraciones agregadas
        DB::table('configurations')->whereIn('group', ['moderation', 'security', 'limits'])->delete();
    }
};
