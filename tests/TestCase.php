<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert against English strings; set locale explicitly
        // so translation resolution is predictable regardless of DB state.
        app()->setLocale('en-US');
        Cache::flush();
    }
}
