<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the real Pixelforge.
        Http::preventStrayRequests();

        // Pages render without a frontend build.
        $this->withoutVite();
    }
}
