<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPasswordRulesAPI extends Command
{
    protected $signature = 'test:password-rules-api';

    protected $description = 'Test password validation rules API';

    public function handle()
    {
        $this->info('Testing password validation rules API...');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/password-validation-rules');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->info("HTTP Status: $httpCode");

        if ($response) {
            $data = json_decode($response, true);
            $this->info('Response:');
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if (isset($data['data'])) {
                $rules = $data['data'];
                $this->info("\nParsed Rules:");
                $this->line('Min Length: '.$rules['minLength']);
                $this->line('Require Special: '.($rules['requireSpecial'] ? 'Yes' : 'No'));
                $this->line('Require Uppercase: '.($rules['requireUppercase'] ? 'Yes' : 'No'));
                $this->line('Require Numbers: '.($rules['requireNumbers'] ? 'Yes' : 'No'));
                $this->line('Message: '.$rules['validationMessage']);
            }
        } else {
            $this->error('No response received');
        }

        return 0;
    }
}
