<?php

namespace App\Console\Commands;

use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestPasswordValidation extends Command
{
    protected $signature = 'test:password-validation';

    protected $description = 'Test dynamic password validation';

    public function handle()
    {
        $this->info('Testing password validation with current security settings...');

        $controller = app(RegisteredUserController::class);

        // Test with weak password (should fail with current settings: min 12 chars)
        $this->info("\n1. Testing with weak password (8 chars, no special/uppercase):");
        $weakRequest = new Request([
            'name' => 'Test User',
            'email' => 'test'.time().'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        try {
            $response = $controller->store($weakRequest);
            $data = $response->getData(true);
            if (isset($data['access_token'])) {
                $this->error("✗ Weak password accepted (shouldn't happen)");
            } else {
                $this->info('✓ Weak password rejected');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->info('✓ Weak password rejected: '.implode(', ', $e->validator->errors()->all()));
        } catch (\Exception $e) {
            $this->info('✓ Weak password rejected: '.$e->getMessage());
        }

        // Test with strong password (should pass)
        $this->info("\n2. Testing with strong password (12+ chars, special, uppercase, numbers):");
        $strongRequest = new Request([
            'name' => 'Test User 2',
            'email' => 'test'.(time() + 1).'@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        try {
            $response = $controller->store($strongRequest);
            $data = $response->getData(true);
            if (isset($data['access_token'])) {
                $this->info('✓ Strong password accepted');
            } else {
                $this->error('✗ Strong password rejected');
            }
        } catch (\Exception $e) {
            $this->error('✗ Strong password rejected: '.$e->getMessage());
        }

        return 0;
    }
}
