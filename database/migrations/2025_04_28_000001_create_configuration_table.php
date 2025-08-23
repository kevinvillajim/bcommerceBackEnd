<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('text'); // text, number, boolean, json
            $table->timestamps();
        });

        // Insertar todas las configuraciones predeterminadas
        $this->seedGeneralSettings();
        $this->seedEmailSettings();
        $this->seedSecuritySettings();
        $this->seedPaymentSettings();
        $this->seedIntegrationSettings();
        $this->seedNotificationSettings();
        $this->seedBackupSettings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }

    /**
     * Seed general settings
     */
    private function seedGeneralSettings(): void
    {
        DB::table('configurations')->insert([
            [
                'key' => 'ratings.auto_approve_all',
                'value' => 'false',
                'description' => 'Aprobar automáticamente todas las valoraciones',
                'group' => 'ratings',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ratings.auto_approve_threshold',
                'value' => '2',
                'description' => 'Umbral de estrellas para aprobación automática (1-5). Las valoraciones por encima de este valor se aprobarán automáticamente.',
                'group' => 'ratings',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.siteName',
                'value' => 'B-Commerce',
                'description' => 'Nombre que aparecerá en el título de la página y correos electrónicos',
                'group' => 'general',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.siteDescription',
                'value' => 'Plataforma de comercio electrónico especializada en Ecuador',
                'description' => 'Breve descripción para SEO y compartir en redes sociales',
                'group' => 'general',
                'type' => 'textarea',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.contactEmail',
                'value' => 'admin@bcommerce.com',
                'description' => 'Se muestra a los clientes para soporte y contacto',
                'group' => 'general',
                'type' => 'email',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.adminEmail',
                'value' => 'admin@bcommerce.com',
                'description' => 'Recibe notificaciones administrativas y alertas',
                'group' => 'general',
                'type' => 'email',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.itemsPerPage',
                'value' => '12',
                'description' => 'Número de productos a mostrar por página en las listas',
                'group' => 'general',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.maintenanceMode',
                'value' => 'false',
                'description' => 'Cuando está activado, solo los administradores pueden acceder al sitio',
                'group' => 'general',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.enableRegistration',
                'value' => 'true',
                'description' => 'Los usuarios pueden crear nuevas cuentas en el sitio',
                'group' => 'general',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.defaultLanguage',
                'value' => 'es',
                'description' => 'Idioma predeterminado del sitio',
                'group' => 'general',
                'type' => 'select',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.defaultCurrency',
                'value' => 'USD',
                'description' => 'Moneda predeterminada del sitio',
                'group' => 'general',
                'type' => 'select',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'general.timeZone',
                'value' => 'America/Guayaquil',
                'description' => 'Zona horaria del sitio',
                'group' => 'general',
                'type' => 'select',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Seed email settings
     */
    private function seedEmailSettings(): void
    {
        DB::table('configurations')->insert([
            [
                'key' => 'email.smtpHost',
                'value' => 'smtp.mailserver.com',
                'description' => 'Servidor SMTP para envío de correos',
                'group' => 'email',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.smtpPort',
                'value' => '587',
                'description' => 'Puerto SMTP',
                'group' => 'email',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.smtpUsername',
                'value' => 'noreply@bcommerce.com',
                'description' => 'Usuario SMTP',
                'group' => 'email',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.smtpPassword',
                'value' => '************',
                'description' => 'Contraseña SMTP',
                'group' => 'email',
                'type' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.smtpEncryption',
                'value' => 'tls',
                'description' => 'Cifrado SMTP',
                'group' => 'email',
                'type' => 'select',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.senderName',
                'value' => 'B-Commerce System',
                'description' => 'Nombre que aparecerá como remitente de correos',
                'group' => 'email',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.senderEmail',
                'value' => 'noreply@bcommerce.com',
                'description' => 'Dirección desde la que se enviarán los correos',
                'group' => 'email',
                'type' => 'email',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.notificationEmails',
                'value' => 'true',
                'description' => 'Enviar correos de notificación del sistema',
                'group' => 'email',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.welcomeEmail',
                'value' => 'true',
                'description' => 'Enviar correo de bienvenida a nuevos usuarios',
                'group' => 'email',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.orderConfirmationEmail',
                'value' => 'true',
                'description' => 'Enviar correos de confirmación de pedidos',
                'group' => 'email',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email.passwordResetEmail',
                'value' => 'true',
                'description' => 'Enviar correos de restablecimiento de contraseña',
                'group' => 'email',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Seed security settings
     */
    private function seedSecuritySettings(): void
    {
        DB::table('configurations')->insert([
            [
                'key' => 'security.passwordMinLength',
                'value' => '8',
                'description' => 'Longitud mínima de contraseña',
                'group' => 'security',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.passwordRequireSpecial',
                'value' => 'true',
                'description' => 'Requerir caracteres especiales en contraseña',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.passwordRequireUppercase',
                'value' => 'true',
                'description' => 'Requerir mayúsculas en contraseña',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.passwordRequireNumbers',
                'value' => 'true',
                'description' => 'Requerir números en contraseña',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.accountLockAttempts',
                'value' => '5',
                'description' => 'Número de intentos fallidos antes de bloquear cuenta',
                'group' => 'security',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.sessionTimeout',
                'value' => '120',
                'description' => 'Tiempo de sesión en minutos',
                'group' => 'security',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.enableTwoFactor',
                'value' => 'false',
                'description' => 'Habilitar autenticación de dos factores',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.requireEmailVerification',
                'value' => 'true',
                'description' => 'Los usuarios deben verificar su correo al registrarse',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.adminIpRestriction',
                'value' => '',
                'description' => 'Lista de IPs permitidas para acceso admin (separadas por coma)',
                'group' => 'security',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.enableCaptcha',
                'value' => 'true',
                'description' => 'Habilitar CAPTCHA en formularios',
                'group' => 'security',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Seed payment settings
     */
    private function seedPaymentSettings(): void
    {
        DB::table('configurations')->insert([
            [
                'key' => 'payment.currencySymbol',
                'value' => '$',
                'description' => 'Símbolo de moneda',
                'group' => 'payment',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.currencyCode',
                'value' => 'USD',
                'description' => 'Código de moneda',
                'group' => 'payment',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.enablePayPal',
                'value' => 'true',
                'description' => 'Habilitar pagos con PayPal',
                'group' => 'payment',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.payPalClientId',
                'value' => 'paypal-client-id-here',
                'description' => 'Cliente ID de PayPal',
                'group' => 'payment',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.payPalClientSecret',
                'value' => '************',
                'description' => 'Cliente Secret de PayPal',
                'group' => 'payment',
                'type' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.payPalSandboxMode',
                'value' => 'true',
                'description' => 'Usar entorno de pruebas (sandbox) de PayPal',
                'group' => 'payment',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.enableCreditCard',
                'value' => 'true',
                'description' => 'Habilitar pagos con tarjeta de crédito (Stripe)',
                'group' => 'payment',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.stripePublicKey',
                'value' => 'pk_test_sample-key',
                'description' => 'Clave pública de Stripe',
                'group' => 'payment',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.stripeSecretKey',
                'value' => '************',
                'description' => 'Clave secreta de Stripe',
                'group' => 'payment',
                'type' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.stripeSandboxMode',
                'value' => 'true',
                'description' => 'Usar entorno de pruebas de Stripe',
                'group' => 'payment',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.enableLocalPayments',
                'value' => 'true',
                'description' => 'Habilitar transferencia bancaria / pago contra entrega',
                'group' => 'payment',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment.taxRate',
                'value' => '12',
                'description' => 'Tasa de impuesto (%)',
                'group' => 'payment',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Seed integration settings
     */
    private function seedIntegrationSettings(): void
    {
        DB::table('configurations')->insert([
            [
                'key' => 'integrations.googleAnalyticsId',
                'value' => 'UA-XXXXXXXXX-X',
                'description' => 'ID de Google Analytics',
                'group' => 'integrations',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.enableGoogleAnalytics',
                'value' => 'false',
                'description' => 'Habilitar Google Analytics',
                'group' => 'integrations',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.facebookPixelId',
                'value' => '',
                'description' => 'ID de Facebook Pixel',
                'group' => 'integrations',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.enableFacebookPixel',
                'value' => 'false',
                'description' => 'Habilitar Facebook Pixel',
                'group' => 'integrations',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.recaptchaSiteKey',
                'value' => '6LxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxE',
                'description' => 'Site Key de reCAPTCHA',
                'group' => 'integrations',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.recaptchaSecretKey',
                'value' => '************',
                'description' => 'Secret Key de reCAPTCHA',
                'group' => 'integrations',
                'type' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.enableHotjar',
                'value' => 'false',
                'description' => 'Habilitar Hotjar',
                'group' => 'integrations',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.hotjarId',
                'value' => '',
                'description' => 'ID de Hotjar',
                'group' => 'integrations',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.enableChatbot',
                'value' => 'false',
                'description' => 'Habilitar Chat en vivo',
                'group' => 'integrations',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'integrations.chatbotScript',
                'value' => '',
                'description' => 'Script del proveedor de chat',
                'group' => 'integrations',
                'type' => 'textarea',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Seed notification settings
     */
    private function seedNotificationSettings(): void
    {
        DB::table('configurations')->insert([
            // Notificaciones para administradores
            [
                'key' => 'notifications.adminNewOrder',
                'value' => 'true',
                'description' => 'Notificar a administradores sobre nuevos pedidos',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.adminNewUser',
                'value' => 'true',
                'description' => 'Notificar a administradores sobre nuevos usuarios',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.adminLowStock',
                'value' => 'true',
                'description' => 'Notificar a administradores sobre stock bajo',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.adminNewReview',
                'value' => 'true',
                'description' => 'Notificar a administradores sobre nuevas valoraciones',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.adminFailedPayment',
                'value' => 'true',
                'description' => 'Notificar a administradores sobre pagos fallidos',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Notificaciones para vendedores
            [
                'key' => 'notifications.sellerNewOrder',
                'value' => 'true',
                'description' => 'Notificar a vendedores sobre nuevos pedidos',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.sellerLowStock',
                'value' => 'true',
                'description' => 'Notificar a vendedores sobre stock bajo',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.sellerProductReview',
                'value' => 'true',
                'description' => 'Notificar a vendedores sobre valoraciones de productos',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.sellerMessageReceived',
                'value' => 'true',
                'description' => 'Notificar a vendedores sobre mensajes recibidos',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.sellerReturnRequest',
                'value' => 'true',
                'description' => 'Notificar a vendedores sobre solicitudes de devolución',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Notificaciones para usuarios
            [
                'key' => 'notifications.userOrderStatus',
                'value' => 'true',
                'description' => 'Notificar a usuarios sobre cambios en estado de pedidos',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.userDeliveryUpdates',
                'value' => 'true',
                'description' => 'Notificar a usuarios sobre actualizaciones de entrega',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.userPromotions',
                'value' => 'false',
                'description' => 'Notificar a usuarios sobre promociones y ofertas',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.userAccountChanges',
                'value' => 'true',
                'description' => 'Notificar a usuarios sobre cambios en su cuenta',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notifications.userPasswordChanges',
                'value' => 'true',
                'description' => 'Notificar a usuarios sobre cambios de contraseña',
                'group' => 'notifications',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Seed backup settings
     */
    private function seedBackupSettings(): void
    {
        DB::table('configurations')->insert([
            [
                'key' => 'backup.automaticBackups',
                'value' => 'true',
                'description' => 'Habilitar respaldos automáticos',
                'group' => 'backup',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.backupFrequency',
                'value' => 'daily',
                'description' => 'Frecuencia de respaldo (hourly, daily, weekly, monthly)',
                'group' => 'backup',
                'type' => 'select',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.backupTime',
                'value' => '02:00',
                'description' => 'Hora del día para realizar el respaldo (zona horaria del servidor)',
                'group' => 'backup',
                'type' => 'time',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.backupRetention',
                'value' => '30',
                'description' => 'Número de días que se conservarán los respaldos',
                'group' => 'backup',
                'type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.includeMedia',
                'value' => 'true',
                'description' => 'Incluir archivos multimedia en el respaldo',
                'group' => 'backup',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.backupToCloud',
                'value' => 'false',
                'description' => 'Respaldar a almacenamiento en la nube',
                'group' => 'backup',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.cloudProvider',
                'value' => 'none',
                'description' => 'Proveedor de nube para respaldos',
                'group' => 'backup',
                'type' => 'select',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.cloudApiKey',
                'value' => '',
                'description' => 'API Key / Access Key del proveedor de nube',
                'group' => 'backup',
                'type' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.cloudSecret',
                'value' => '',
                'description' => 'Secret Key / Access Secret del proveedor de nube',
                'group' => 'backup',
                'type' => 'password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.cloudBucket',
                'value' => '',
                'description' => 'Bucket / Contenedor donde se almacenarán los respaldos',
                'group' => 'backup',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'backup.lastBackupDate',
                'value' => '2025-04-01 02:00:00',
                'description' => 'Fecha y hora del último respaldo realizado',
                'group' => 'backup',
                'type' => 'datetime',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};
