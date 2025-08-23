<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    // This trait should be defined in your Laravel application
    use CreatesApplication;

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
