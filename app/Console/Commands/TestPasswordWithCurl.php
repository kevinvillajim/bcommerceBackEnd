<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPasswordWithCurl extends Command
{
    protected $signature = 'test:password-curl';

    protected $description = 'Test password validation via HTTP call';

    public function handle()
    {
        $this->info('Testing password validation via HTTP...');

        // Test 1: Valid password (should succeed)
        $this->info("\n1. Testing valid password (ValidPassword123!):");
        $validData = json_encode([
            'name' => 'Test User Valid',
            'email' => 'testvalid'.time().'@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
        ]);

        $validResponse = $this->makeHttpRequest($validData);
        $this->info('Valid password response: '.$validResponse);

        // Test 2: Short password (should fail)
        $this->info("\n2. Testing short password (123456A@):");
        $shortData = json_encode([
            'name' => 'Test User Short',
            'email' => 'testshort'.time().'@example.com',
            'password' => '123456A@',
            'password_confirmation' => '123456A@',
        ]);

        $shortResponse = $this->makeHttpRequest($shortData);
        $this->info('Short password response: '.$shortResponse);

        return 0;
    }

    private function makeHttpRequest($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/register');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return "HTTP $httpCode: ".($response ?: 'No response');
    }
}
