<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ CONFIGURACIONES CRÍTICAS DE RESPALDO
 *
 * Este seeder contiene las configuraciones esenciales del sistema
 * para recuperarse de pérdidas accidentales de la base de datos.
 *
 * NUNCA eliminar este archivo.
 */
class CriticalConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configurations = [
            // Ratings
            ['key' => 'ratings.auto_approve_all', 'value' => 'false', 'description' => 'Aprobar automáticamente todas las valoraciones', 'category' => 'ratings', 'type' => 'boolean'],
            ['key' => 'ratings.auto_approve_threshold', 'value' => '2', 'description' => 'Umbral de estrellas para aprobación automática (1-5). Las valoraciones por encima de este valor se aprobarán automáticamente.', 'category' => 'ratings', 'type' => 'number'],

            // General
            ['key' => 'general.siteName', 'value' => 'Comersia', 'description' => 'Nombre que aparecerá en el título de la página y correos electrónicos', 'category' => 'general', 'type' => 'text'],
            ['key' => 'general.siteDescription', 'value' => 'Plataforma de comercio electrónico especializada en Ecuador', 'description' => 'Breve descripción para SEO y compartir en redes sociales', 'category' => 'general', 'type' => 'textarea'],
            ['key' => 'general.contactEmail', 'value' => 'admin@bcommerce.com', 'description' => 'Se muestra a los clientes para soporte y contacto', 'category' => 'general', 'type' => 'email'],
            ['key' => 'general.adminEmail', 'value' => 'admin@bcommerce.com', 'description' => 'Recibe notificaciones administrativas y alertas', 'category' => 'general', 'type' => 'email'],
            ['key' => 'general.itemsPerPage', 'value' => '12', 'description' => 'Número de productos a mostrar por página en las listas', 'category' => 'general', 'type' => 'number'],
            ['key' => 'general.maintenanceMode', 'value' => 'false', 'description' => 'Cuando está activado, solo los administradores pueden acceder al sitio', 'category' => 'general', 'type' => 'boolean'],
            ['key' => 'general.enableRegistration', 'value' => 'true', 'description' => 'Los usuarios pueden crear nuevas cuentas en el sitio', 'category' => 'general', 'type' => 'boolean'],
            ['key' => 'general.defaultLanguage', 'value' => 'es', 'description' => 'Idioma predeterminado del sitio', 'category' => 'general', 'type' => 'select'],
            ['key' => 'general.defaultCurrency', 'value' => 'USD', 'description' => 'Moneda predeterminada del sitio', 'category' => 'general', 'type' => 'select'],
            ['key' => 'general.timeZone', 'value' => 'America/Guayaquil', 'description' => 'Zona horaria del sitio', 'category' => 'general', 'type' => 'select'],

            // Email
            ['key' => 'email.smtpHost', 'value' => 'mail.comersia.app', 'description' => 'Servidor SMTP para envío de correos', 'category' => 'email', 'type' => 'text'],
            ['key' => 'email.smtpPort', 'value' => '465', 'description' => 'Puerto SMTP', 'category' => 'email', 'type' => 'number'],
            ['key' => 'email.smtpUsername', 'value' => 'info@comersia.app', 'description' => 'Usuario SMTP', 'category' => 'email', 'type' => 'text'],
            ['key' => 'email.smtpPassword', 'value' => env('MAIL_PASSWORD', ''), 'description' => 'Contraseña SMTP', 'category' => 'email', 'type' => 'password'],
            ['key' => 'email.smtpEncryption', 'value' => 'ssl', 'description' => 'Cifrado SMTP', 'category' => 'email', 'type' => 'select'],
            ['key' => 'email.senderName', 'value' => 'Comersia App', 'description' => 'Nombre que aparecerá como remitente de correos', 'category' => 'email', 'type' => 'text'],
            ['key' => 'email.senderEmail', 'value' => 'info@comersia.app', 'description' => 'Dirección desde la que se enviarán los correos', 'category' => 'email', 'type' => 'email'],
            ['key' => 'email.notificationEmails', 'value' => 'true', 'description' => 'Enviar correos de notificación del sistema', 'category' => 'email', 'type' => 'boolean'],
            ['key' => 'email.welcomeEmail', 'value' => 'true', 'description' => 'Enviar correo de bienvenida a nuevos usuarios', 'category' => 'email', 'type' => 'boolean'],
            ['key' => 'email.orderConfirmationEmail', 'value' => 'true', 'description' => 'Enviar correos de confirmación de pedidos', 'category' => 'email', 'type' => 'boolean'],
            ['key' => 'email.passwordResetEmail', 'value' => 'true', 'description' => 'Enviar correos de restablecimiento de contraseña', 'category' => 'email', 'type' => 'boolean'],
            ['key' => 'email.bypassVerification', 'value' => '1', 'description' => 'Bypass email verification for development/testing', 'category' => 'email', 'type' => 'boolean'],
            ['key' => 'email.requireVerification', 'value' => 'false', 'description' => 'Require email verification for new accounts', 'category' => 'email', 'type' => 'boolean'],
            ['key' => 'email.verificationTimeout', 'value' => '24', 'description' => 'Email verification token timeout in hours', 'category' => 'email', 'type' => 'number'],

            // Security
            ['key' => 'security.passwordMinLength', 'value' => '8', 'description' => 'Longitud mínima requerida para contraseñas de usuario', 'category' => 'security', 'type' => 'number'],
            ['key' => 'security.passwordRequireSpecial', 'value' => 'true', 'description' => 'Requerir al menos un carácter especial en las contraseñas', 'category' => 'security', 'type' => 'boolean'],
            ['key' => 'security.passwordRequireUppercase', 'value' => 'true', 'description' => 'Requerir al menos una letra mayúscula en las contraseñas', 'category' => 'security', 'type' => 'boolean'],
            ['key' => 'security.passwordRequireNumbers', 'value' => 'true', 'description' => 'Requerir al menos un número en las contraseñas', 'category' => 'security', 'type' => 'boolean'],
            ['key' => 'security.accountLockAttempts', 'value' => '5', 'description' => 'Número de intentos de login fallidos antes de bloquear temporalmente la cuenta', 'category' => 'security', 'type' => 'number'],
            ['key' => 'security.sessionTimeout', 'value' => '120', 'description' => 'Tiempo de inactividad en minutos antes de cerrar sesión automáticamente', 'category' => 'security', 'type' => 'number'],
            ['key' => 'security.enableTwoFactor', 'value' => 'false', 'description' => 'Habilitar autenticación de dos factores para todos los usuarios', 'category' => 'security', 'type' => 'boolean'],
            ['key' => 'security.requireEmailVerification', 'value' => 'true', 'description' => 'Requerir verificación de email para cuentas nuevas', 'category' => 'security', 'type' => 'boolean'],
            ['key' => 'security.adminIpRestriction', 'value' => '', 'description' => 'Lista de IPs permitidas para acceso administrativo (una por línea, vacío = sin restricción)', 'category' => 'security', 'type' => 'textarea'],
            ['key' => 'security.enableCaptcha', 'value' => 'false', 'description' => 'Habilitar CAPTCHA en formularios de registro y login', 'category' => 'security', 'type' => 'boolean'],

            // Payment
            ['key' => 'payment.currencySymbol', 'value' => '$', 'description' => 'Símbolo de moneda', 'category' => 'payment', 'type' => 'text'],
            ['key' => 'payment.currencyCode', 'value' => 'USD', 'description' => 'Código de moneda', 'category' => 'payment', 'type' => 'text'],
            ['key' => 'payment.taxRate', 'value' => '15', 'description' => 'Tasa de impuesto (%) - IVA Ecuador', 'category' => 'payment', 'type' => 'number'],

            // Volume Discounts
            ['key' => 'volume_discounts.enabled', 'value' => 'true', 'description' => 'Habilitar descuentos por volumen en toda la tienda', 'category' => 'volume_discounts', 'type' => 'boolean'],
            ['key' => 'volume_discounts.stackable', 'value' => 'true', 'description' => 'Permitir que los descuentos por volumen se combinen con otros descuentos', 'category' => 'volume_discounts', 'type' => 'boolean'],
            ['key' => 'volume_discounts.default_tiers', 'value' => '[{"quantity":3,"discount":5,"label":"Descuento 3+"},{"quantity":6,"discount":10,"label":"Descuento 6+"},{"quantity":12,"discount":15,"label":"Descuento 12+"}]', 'description' => 'Niveles de descuento por defecto para nuevos productos', 'category' => 'volume_discounts', 'type' => 'json'],
            ['key' => 'volume_discounts.show_savings_message', 'value' => 'true', 'description' => 'Mostrar mensaje de ahorro en páginas de producto', 'category' => 'volume_discounts', 'type' => 'boolean'],

            // Shipping
            ['key' => 'shipping.free_threshold', 'value' => '50', 'description' => 'Umbral en USD para envío gratis', 'category' => 'shipping', 'type' => 'decimal'],
            ['key' => 'shipping.default_cost', 'value' => '5', 'description' => 'Costo de envío por defecto en USD', 'category' => 'shipping', 'type' => 'decimal'],
            ['key' => 'shipping.enabled', 'value' => 'true', 'description' => 'Habilitar cálculo de costos de envío', 'category' => 'shipping', 'type' => 'boolean'],
            ['key' => 'shipping.seller_percentage', 'value' => '80.0', 'description' => 'Porcentaje del costo de envío que recibe un seller cuando hay un solo vendedor', 'category' => 'general', 'type' => 'text'],
            ['key' => 'shipping.max_seller_percentage', 'value' => '40.0', 'description' => 'Máximo porcentaje del costo de envío que puede recibir un solo vendedor cuando hay múltiples', 'category' => 'general', 'type' => 'text'],

            // Platform
            ['key' => 'platform.commission_rate', 'value' => '10.0', 'description' => 'Porcentaje de comisión que cobra la plataforma a los vendedores', 'category' => 'general', 'type' => 'text'],

            // Development
            ['key' => 'development.mode', 'value' => 'false', 'description' => 'Enable development mode for system updates and maintenance', 'category' => 'development', 'type' => 'boolean'],
            ['key' => 'development.allowAdminOnlyAccess', 'value' => 'false', 'description' => 'Restrict access to administrators only during maintenance', 'category' => 'development', 'type' => 'boolean'],

            // Moderation
            ['key' => 'moderation.userStrikesThreshold', 'value' => '3', 'description' => 'Número de strikes que puede acumular un usuario antes de ser bloqueado automáticamente', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.contactScorePenalty', 'value' => '3', 'description' => 'Puntos añadidos al score de contacto por patrones sospechosos', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.businessScoreBonus', 'value' => '15', 'description' => 'Puntos añadidos al score de negocio cuando se detectan términos comerciales legítimos', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.contactPenaltyHeavy', 'value' => '20', 'description' => 'Penalización severa aplicada cuando se detectan indicadores claros de contacto', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.minimumContactScore', 'value' => '8', 'description' => 'Score mínimo para considerar que un mensaje contiene información de contacto', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.scoreDifferenceThreshold', 'value' => '5', 'description' => 'Diferencia mínima entre score de contacto y negocio para activar moderación', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.consecutiveNumbersLimit', 'value' => '7', 'description' => 'Número de dígitos consecutivos que activan la detección de teléfonos', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.numbersWithContextLimit', 'value' => '3', 'description' => 'Número de dígitos que, junto con palabras de contacto, activan la detección', 'category' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.lowStockThreshold', 'value' => '10', 'description' => 'Cantidad mínima de productos en stock antes de mostrar aviso de stock bajo', 'category' => 'moderation', 'type' => 'number'],

            // Limits
            ['key' => 'limits.cartMaxItems', 'value' => '100', 'description' => 'Número máximo de productos diferentes que puede tener un carrito', 'category' => 'limits', 'type' => 'number'],
            ['key' => 'limits.cartMaxQuantityPerItem', 'value' => '99', 'description' => 'Cantidad máxima que se puede agregar de un mismo producto', 'category' => 'limits', 'type' => 'number'],
            ['key' => 'limits.orderTimeout', 'value' => '15', 'description' => 'Tiempo límite en minutos para completar el proceso de pago', 'category' => 'limits', 'type' => 'number'],
            ['key' => 'limits.recommendationLimit', 'value' => '10', 'description' => 'Número de productos recomendados mostrados por defecto', 'category' => 'limits', 'type' => 'number'],
            ['key' => 'limits.maxRecommendationResults', 'value' => '10000', 'description' => 'Límite máximo de resultados que puede procesar el sistema de recomendaciones', 'category' => 'limits', 'type' => 'number'],
            ['key' => 'limits.tokenRefreshThreshold', 'value' => '15', 'description' => 'Minutos antes de expiración para renovar tokens automáticamente', 'category' => 'limits', 'type' => 'number'],
        ];

        // Usar upsert para evitar duplicados y actualizar si ya existen
        foreach ($configurations as $config) {
            DB::table('configurations')->updateOrInsert(
                ['key' => $config['key']],
                [
                    'value' => $config['value'],
                    'description' => $config['description'],
                    'group' => $config['category'], // La tabla usa 'group' no 'category'
                    'type' => $config['type'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->command->info('✅ Configuraciones críticas insertadas/actualizadas: '.count($configurations));
    }
}
