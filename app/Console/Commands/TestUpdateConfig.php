<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\ConfigurationController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestUpdateConfig extends Command
{
    protected $signature = 'test:update-config';

    protected $description = 'Test configuration update functionality';

    public function handle()
    {
        $controller = new ConfigurationController(app(\App\Services\ConfigurationService::class));

        // Simulate request to update password length to 12
        $request = new Request([
            'category' => 'security',
            'configurations' => [
                'passwordMinLength' => 12,
            ],
        ]);

        $response = $controller->updateByCategory($request);

        $this->info('Update response:');
        $this->line(json_encode($response->getData(), JSON_PRETTY_PRINT));

        return 0;
    }
}
