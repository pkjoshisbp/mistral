<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety: ensure tests never use MySQL connection to avoid destructive operations
        $driver = config('database.default');
        if ($driver !== 'sqlite') {
            // Force sqlite if misconfigured
            config()->set('database.default', 'sqlite');
            config()->set('database.connections.sqlite.database', base_path('database/testing.sqlite'));
        }
    }
}
