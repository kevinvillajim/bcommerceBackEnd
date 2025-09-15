<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class EmailConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Este seeder configura automáticamente los valores de correo en la base de datos
     * tomando valores del .env como fuente. Útil para producción y staging.
     */
    public function run(): void
    {
        Log::info('🚀 EmailConfigurationSeeder iniciado');

        // Definir configuraciones de email que deben existir en BD
        $emailConfigurations = [
            'email.smtpHost' => [
                'value' => env('MAIL_HOST', 'mail.comersia.app'),
                'description' => 'Servidor SMTP para envío de correos',
                'type' => 'string',
                'group' => 'email',
            ],
            'email.smtpPort' => [
                'value' => env('MAIL_PORT', 465),
                'description' => 'Puerto del servidor SMTP',
                'type' => 'integer',
                'group' => 'email',
            ],
            'email.smtpUsername' => [
                'value' => env('MAIL_USERNAME', ''),
                'description' => 'Usuario SMTP para autenticación',
                'type' => 'string',
                'group' => 'email',
                'sensitive' => true,
            ],
            'email.smtpPassword' => [
                'value' => env('MAIL_PASSWORD', ''),
                'description' => 'Contraseña SMTP para autenticación',
                'type' => 'string',
                'group' => 'email',
                'sensitive' => true,
            ],
            'email.smtpEncryption' => [
                'value' => env('MAIL_ENCRYPTION', 'ssl'),
                'description' => 'Tipo de encriptación SMTP (tls, ssl, none)',
                'type' => 'string',
                'group' => 'email',
            ],
            'email.senderEmail' => [
                'value' => env('MAIL_FROM_ADDRESS', 'info@comersia.app'),
                'description' => 'Email remitente por defecto',
                'type' => 'email',
                'group' => 'email',
            ],
            'email.senderName' => [
                'value' => env('MAIL_FROM_NAME', env('APP_NAME', 'Comersia App')),
                'description' => 'Nombre del remitente por defecto',
                'type' => 'string',
                'group' => 'email',
            ],
            'email.verificationTimeout' => [
                'value' => 24,
                'description' => 'Horas de validez del token de verificación',
                'type' => 'integer',
                'group' => 'email',
            ],
            'email.notificationEmails' => [
                'value' => true,
                'description' => 'Enviar correos de notificación del sistema',
                'type' => 'boolean',
                'group' => 'email',
            ],
            'email.welcomeEmail' => [
                'value' => true,
                'description' => 'Enviar correo de bienvenida a nuevos usuarios',
                'type' => 'boolean',
                'group' => 'email',
            ],
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($emailConfigurations as $key => $config) {
            try {
                // Verificar si ya existe la configuración
                $existingConfig = Configuration::where('key_name', $key)->first();

                if ($existingConfig) {
                    // Solo actualizar si el valor actual está vacío o es un placeholder
                    $shouldUpdate = empty($existingConfig->value) ||
                                  in_array($existingConfig->value, [
                                      'localhost', 'your-email@gmail.com', 'your-app-password',
                                      'hello@example.com', 'noreply@example.com', 'Example',
                                  ]);

                    if ($shouldUpdate && ! empty($config['value'])) {
                        $existingConfig->update([
                            'value' => $config['value'],
                            'description' => $config['description'] ?? '',
                        ]);
                        $updated++;
                        Log::info("📝 Configuración actualizada: {$key} = {$config['value']}");
                    } else {
                        $skipped++;
                        Log::info("⏭️ Configuración omitida (ya tiene valor válido): {$key}");
                    }
                } else {
                    // Crear nueva configuración
                    Configuration::create([
                        'key_name' => $key,
                        'value' => $config['value'],
                        'description' => $config['description'] ?? '',
                        'type' => $config['type'] ?? 'string',
                        'group' => $config['group'] ?? 'general',
                        'is_sensitive' => $config['sensitive'] ?? false,
                    ]);
                    $created++;
                    Log::info("✅ Configuración creada: {$key} = {$config['value']}");
                }

            } catch (\Exception $e) {
                Log::error("❌ Error procesando configuración {$key}: ".$e->getMessage());
            }
        }

        // Resumen de resultados
        Log::info('🎉 EmailConfigurationSeeder completado:', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_processed' => count($emailConfigurations),
        ]);

        $this->command->info('📧 Email Configuration Seeder completed:');
        $this->command->info("   ✅ Created: {$created}");
        $this->command->info("   📝 Updated: {$updated}");
        $this->command->info("   ⏭️ Skipped: {$skipped}");

        // Validar configuración crítica
        $this->validateCriticalConfig();
    }

    /**
     * Validar que las configuraciones críticas estén presentes
     */
    private function validateCriticalConfig(): void
    {
        $criticalKeys = [
            'email.smtpHost',
            'email.smtpPort',
            'email.senderEmail',
        ];

        $missing = [];
        foreach ($criticalKeys as $key) {
            $config = Configuration::where('key_name', $key)->first();
            if (! $config || empty($config->value)) {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            $this->command->warn('⚠️  Configuraciones críticas faltantes: '.implode(', ', $missing));
            $this->command->warn('   Estas son necesarias para el envío de correos');
        } else {
            $this->command->info('✅ Todas las configuraciones críticas están presentes');
        }
    }
}
