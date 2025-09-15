<?php

namespace App\Console\Commands;

use App\Services\Mail\MailManager;
use Illuminate\Console\Command;

class EmailSystemStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email:status 
                           {--test : Test SMTP connection}
                           {--templates : List available email templates}';

    /**
     * The console command description.
     */
    protected $description = 'Check email system status and configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mailManager = app(MailManager::class);

        $this->info('📧 Email System Status');
        $this->line('');

        // Show current configuration
        $config = $mailManager->getMailConfiguration();
        $this->info('📋 Current Configuration:');
        $this->table(['Setting', 'Value'], [
            ['SMTP Host', $config['host']],
            ['SMTP Port', $config['port']],
            ['Username', $config['username']],
            ['Encryption', $config['encryption']],
            ['From Address', $config['from_address']],
            ['From Name', $config['from_name']],
        ]);

        // Test connection if requested
        if ($this->option('test')) {
            $this->line('');
            $this->info('🔍 Testing SMTP Connection...');

            $result = $mailManager->testConnection();

            if ($result['status'] === 'success') {
                $this->info('✅ SMTP connection successful!');
                if (isset($result['details'])) {
                    $this->line('Connection details:');
                    foreach ($result['details'] as $key => $value) {
                        $this->line("  - {$key}: {$value}");
                    }
                }
            } else {
                $this->error('❌ SMTP connection failed: '.$result['message']);

                return 1;
            }
        }

        // List available templates if requested
        if ($this->option('templates')) {
            $this->line('');
            $this->info('📄 Available Email Templates:');

            $templates = $mailManager->getAvailableTemplates();
            $templateData = [];

            foreach ($templates as $key => $template) {
                $templateData[] = [
                    $key,
                    $template['name'],
                    $template['template'],
                    class_basename($template['mailable']),
                ];
            }

            $this->table(
                ['Key', 'Name', 'Template Path', 'Mailable Class'],
                $templateData
            );
        }

        $this->line('');
        $this->info('💡 Available commands:');
        $this->line('  php artisan email:status --test       # Test SMTP connection');
        $this->line('  php artisan email:status --templates   # List email templates');

        return 0;
    }
}
