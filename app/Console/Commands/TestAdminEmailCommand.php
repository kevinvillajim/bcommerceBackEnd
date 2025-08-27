<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestAdminEmailCommand extends Command
{
    protected $signature = 'test:admin-email {email=kevinvillajim@hotmail.com}';
    protected $description = 'Test admin email sending functionality by sending a real email';

    private MailService $mailService;

    public function __construct(MailService $mailService)
    {
        parent::__construct();
        $this->mailService = $mailService;
    }

    public function handle()
    {
        $targetEmail = $this->argument('email');
        
        $this->info("🧪 REAL EMAIL TEST - Testing admin email functionality");
        $this->info("=" . str_repeat("=", 50));

        try {
            // Step 1: Find admin user
            $this->info("👤 Step 1: Finding admin user...");
            $admin = User::where('is_admin', true)->first();
            
            if (!$admin) {
                $this->error("❌ No admin user found in database");
                $this->info("💡 Create an admin user first or run seeders");
                return Command::FAILURE;
            }
            
            $this->info("✅ Admin found: {$admin->name} ({$admin->email})");

            // Step 2: Find or create target user
            $this->info("👤 Step 2: Finding target user...");
            $targetUser = User::where('email', $targetEmail)->first();
            
            if (!$targetUser) {
                $this->warn("⚠️  Target user not found, creating one...");
                $targetUser = User::create([
                    'name' => 'Test User',
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => $targetEmail,
                    'password' => bcrypt('password123'),
                    'email_verified_at' => now(),
                ]);
                $this->info("✅ Target user created: {$targetUser->email}");
            } else {
                $this->info("✅ Target user found: {$targetUser->name} ({$targetUser->email})");
            }

            // Step 3: Test email configuration
            $this->info("⚙️  Step 3: Checking mail configuration...");
            $this->info("📧 Mail driver: " . config('mail.default'));
            $this->info("📧 SMTP host: " . config('mail.mailers.smtp.host'));
            $this->info("📧 SMTP port: " . config('mail.mailers.smtp.port'));
            $this->info("📧 From address: " . config('mail.from.address'));

            // Step 4: Send test email
            $this->info("📧 Step 4: Sending test email...");
            
            $subject = '🧪 REAL TEST - BCommerce Admin Panel Email';
            $message = "¡Hola!\n\nEste es un email REAL enviado desde el comando de testing de BCommerce.\n\nSi recibes este email, significa que:\n✅ El sistema de emails está funcionando\n✅ La configuración SMTP es correcta\n✅ El MailService está operativo\n✅ Los comandos de Laravel funcionan\n\nEste email fue enviado por: {$admin->name} ({$admin->email})\nComando ejecutado: php artisan test:admin-email\n\n¡Saludos!\nSistema BCommerce";

            Log::info('TestAdminEmailCommand: Attempting to send email', [
                'admin_user' => $admin->email,
                'target_user' => $targetUser->email,
                'subject' => $subject,
            ]);

            $result = $this->mailService->sendNotificationEmail(
                $targetUser,
                $subject,
                $message,
                [
                    'email_type' => 'admin_test_command',
                    'sent_by_admin' => true,
                    'admin_name' => $admin->name,
                    'admin_email' => $admin->email,
                    'test_mode' => true,
                    'command_executed' => true
                ]
            );

            // Step 5: Report results
            $this->info("🔍 Step 5: Analyzing results...");

            if ($result) {
                $this->info("🎉 SUCCESS: Email sent successfully!");
                $this->info("📧 Email sent to: {$targetUser->email}");
                $this->info("📮 Check your inbox (and spam folder)");
                $this->info("⏰ Email should arrive within 1-2 minutes");
                
                Log::info('TestAdminEmailCommand: Email sent successfully', [
                    'target_email' => $targetUser->email,
                    'subject' => $subject,
                ]);
                
                return Command::SUCCESS;
            } else {
                $this->error("❌ FAILED: Email was not sent");
                $this->error("📋 Check Laravel logs for detailed error information");
                
                Log::error('TestAdminEmailCommand: Email sending failed', [
                    'target_email' => $targetUser->email,
                    'subject' => $subject,
                ]);
                
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("💥 ERROR: " . $e->getMessage());
            $this->error("📄 File: " . $e->getFile() . ":" . $e->getLine());
            
            Log::error('TestAdminEmailCommand: Exception occurred', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Debugging information
            $this->info("🔍 Debugging information:");
            
            try {
                $this->info("📧 Mail configuration check:");
                $this->info("- Driver: " . config('mail.default'));
                $this->info("- Host: " . config('mail.mailers.smtp.host', 'not_set'));
                $this->info("- Port: " . config('mail.mailers.smtp.port', 'not_set'));
                $this->info("- Username: " . (config('mail.mailers.smtp.username') ? '***set***' : 'not_set'));
                $this->info("- Password: " . (config('mail.mailers.smtp.password') ? '***set***' : 'not_set'));
            } catch (\Exception $configError) {
                $this->error("❌ Could not read mail configuration: " . $configError->getMessage());
            }
            
            return Command::FAILURE;
        }
    }
}