<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure storage directories exist
        $directories = [
            'app/public',
            'app/public/categories',
            'app/public/products',
        ];

        foreach ($directories as $directory) {
            if (! Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }
        }
    }
}
