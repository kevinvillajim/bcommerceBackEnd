<?php

namespace App\Console\Commands;

use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestRegisterAPI extends Command
{
    protected $signature = 'test:register-api';

    protected $description = 'Test registration API directly';

    public function handle()
    {
        $this->info('Testing registration API with current settings...');

        $controller = app(RegisteredUserController::class);

        // Test with valid password (12 chars with requirements)
        $this->info("\n1. Testing with valid password (12+ chars, meets all requirements):");
        $validRequest = new Request([
            'name' => 'Test User API',
            'email' => 'testapi'.time().'@example.com',
            'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ]);

        try {
            $response = $controller->store($validRequest);
            $data = $response->getData(true);
            $status = $response->getStatusCode();

            if ($status === 201 && isset($data['access_token'])) {
                $this->info("✓ Valid password registration successful (status $status)");
            } else {
                $this->error("✗ Valid password registration failed (status $status): ".json_encode($data));
            }
        } catch (\Exception $e) {
            $this->error('✗ Valid password registration exception: '.$e->getMessage());
        }

        // Test with weak password (should fail - only 8 chars, needs 12)
        $this->info("\n2. Testing with short password (123456A@ - only 8 chars, needs 12):");
        $weakRequest = new Request([
            'name' => 'Test User Weak',
            'email' => 'testweak'.time().'@example.com',
            'password' => '123456A@',
            'password_confirmation' => '123456A@',
        ]);

        try {
            $response = $controller->store($weakRequest);
            $data = $response->getData(true);
            $status = $response->getStatusCode();

            if ($status === 422) {
                $this->info("✓ Short password correctly rejected (status $status)");
                if (isset($data['errors']) && isset($data['errors']['password'])) {
                    $this->info('   Error message: '.implode(', ', $data['errors']['password']));
                }
            } else {
                $this->error("✗ Short password incorrectly accepted (status $status)");
            }
        } catch (\Exception $e) {
            $this->info('✓ Short password rejected with exception: '.$e->getMessage());
        }

        // Test with another weak password (no uppercase)
        $this->info("\n3. Testing with password missing uppercase (testpass123@):");
        $noUpperRequest = new Request([
            'name' => 'Test User No Upper',
            'email' => 'testnoupper'.time().'@example.com',
            'password' => 'testpass123@',
            'password_confirmation' => 'testpass123@',
        ]);

        try {
            $response = $controller->store($noUpperRequest);
            $data = $response->getData(true);
            $status = $response->getStatusCode();

            if ($status === 422) {
                $this->info("✓ Password without uppercase correctly rejected (status $status)");
                if (isset($data['errors']) && isset($data['errors']['password'])) {
                    $this->info('   Error message: '.implode(', ', $data['errors']['password']));
                }
            } else {
                $this->error("✗ Password without uppercase incorrectly accepted (status $status)");
            }
        } catch (\Exception $e) {
            $this->info('✓ Password without uppercase rejected with exception: '.$e->getMessage());
        }

        return 0;
    }
}
