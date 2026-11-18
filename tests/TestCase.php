<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    public const PNG = "\x89PNG\r\n\x1a\nfake-image-bytes";

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['pixelforge.promptedit.dev/images/*' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png'])]);
        Storage::fake('local');

        $this->withoutVite();
    }
}
