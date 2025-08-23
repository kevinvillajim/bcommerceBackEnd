<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCompleteRegistration extends Command
{
    protected $signature = 'test:complete-registration';

    protected $description = 'Test complete registration flow with dynamic validation';

    public function handle()
    {
        $this->info('=== TESTING COMPLETE REGISTRATION FLOW ===');

        // 1. First, get validation rules
        $this->info("\n1. Getting password validation rules...");
        $rulesResponse = $this->makeRequest('GET', 'http://127.0.0.1:8000/api/auth/password-validation-rules');

        if ($rulesResponse['status'] === 200) {
            $rules = json_decode($rulesResponse['body'], true);
            $this->info('✓ Rules obtained successfully');
            $this->line('   Min Length: '.$rules['data']['minLength']);
            $this->line('   Message: '.$rules['data']['validationMessage']);
        } else {
            $this->error('✗ Failed to get rules: '.$rulesResponse['body']);

            return 1;
        }

        // 2. Test with invalid password (too short)
        $this->info("\n2. Testing registration with short password (123456A@)...");
        $invalidData = json_encode([
            'name' => 'Test User Short',
            'email' => 'testshort'.time().'@example.com',
            'password' => '123456A@',
            'password_confirmation' => '123456A@',
        ]);

        $invalidResponse = $this->makeRequest('POST', 'http://127.0.0.1:8000/api/auth/register', $invalidData);

        if ($invalidResponse['status'] === 422) {
            $errorData = json_decode($invalidResponse['body'], true);
            $this->info('✓ Short password correctly rejected (422)');
            if (isset($errorData['errors']['password'])) {
                $this->line('   Error: '.$errorData['errors']['password'][0]);
            }
        } else {
            $this->error('✗ Short password should have been rejected');
        }

        // 3. Test with valid password
        $this->info("\n3. Testing registration with valid password (ValidPassword123!)...");
        $validData = json_encode([
            'name' => 'Test User Valid',
            'email' => 'testvalid'.time().'@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
        ]);

        $validResponse = $this->makeRequest('POST', 'http://127.0.0.1:8000/api/auth/register', $validData);

        if ($validResponse['status'] === 201) {
            $successData = json_decode($validResponse['body'], true);
            $this->info('✓ Valid password registration successful (201)');
            $this->line('   User ID: '.$successData['user']['id']);
            $this->line('   User Email: '.$successData['user']['email']);
        } else {
            $this->error('✗ Valid password registration failed: '.$validResponse['body']);
        }

        $this->info("\n=== REGISTRATION FLOW TEST COMPLETE ===");

        return 0;
    }

    private function makeRequest($method, $url, $data = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => $response ?: 'No response',
        ];
    }
}
