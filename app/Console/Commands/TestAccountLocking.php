<?php

namespace App\Console\Commands;

use App\Http\Controllers\AuthController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestAccountLocking extends Command
{
    protected $signature = 'test:account-locking';

    protected $description = 'Test account locking functionality';

    public function handle()
    {
        $this->info('Testing account locking with current security settings...');

        $controller = app(AuthController::class);
        $email = 'locktest@example.com';

        $this->info("\nTesting failed login attempts for: $email");

        // Try 6 failed login attempts (current config is 5 max attempts)
        for ($i = 1; $i <= 6; $i++) {
            $this->info("\nAttempt $i:");

            $request = new Request([
                'email' => $email,
                'password' => 'wrongpassword',
            ]);

            try {
                $response = $controller->login($request);
                $data = $response->getData(true);
                $status = $response->getStatusCode();

                if ($status === 423) {
                    $this->info('✓ Account locked (status 423): '.($data['error'] ?? 'Account locked'));
                } else {
                    $this->info("→ Status $status: ".($data['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $this->info('→ Exception: '.$e->getMessage());
            }
        }

        return 0;
    }
}
