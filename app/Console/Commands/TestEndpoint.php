<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\ConfigurationController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestEndpoint extends Command
{
    protected $signature = 'test:endpoint {category}';

    protected $description = 'Test configuration endpoint';

    public function handle()
    {
        $category = $this->argument('category');

        $controller = new ConfigurationController(app(\App\Services\ConfigurationService::class));

        $request = new Request(['category' => $category]);
        $response = $controller->getByCategory($request);

        $this->info("Response for category: {$category}");
        $this->line(json_encode($response->getData(), JSON_PRETTY_PRINT));

        return 0;
    }
}
