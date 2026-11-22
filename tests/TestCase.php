<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Default all tests to "not installed" so EnsureInstalled redirects to /setup.
        // Individual tests that need installed=true override this themselves.
        config([
            'nexvary.installed' => false,
            'inertia.testing.page_paths' => [resource_path('js/pages')],
        ]);
    }
}
