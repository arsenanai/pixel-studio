<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

function quoteAs(?User $user, array $input): TestResponse
{
    $request = $user ? test()->actingAs($user) : test();

    return $request->postJson('/quote', $input);
}

it('quotes the docs example as a range with a breakdown', function () {
    fakePricing();

    $response = quoteAs(User::factory()->create(), [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ]);

    $response->assertOk()
        ->assertJsonPath('charge.min', ['nano' => '357781250', 'display' => '$0.35778125'])
        ->assertJsonPath('charge.max', ['nano' => '467343750', 'display' => '$0.46734375'])
        ->assertJsonPath('providerCost.max.nano', '373875000')
        ->assertJsonPath('markup.max.nano', '93468750')
        ->assertJsonPath('lines.3.type', 'reasoning_tokens')
        ->assertJsonPath('lines.3.tokens', ['min' => 1_000, 'max' => 8_000]);
});

it('quotes before the prompt has been written', function () {
    fakePricing();

    quoteAs(User::factory()->create(), ['size' => '1024x1024', 'quality' => 'low'])
        ->assertOk()
        ->assertJsonPath('lines.0.tokens', ['min' => 0, 'max' => 0]);
});

it('rejects options Pixelforge does not offer', function (array $input, string $field) {
    fakePricing();

    quoteAs(User::factory()->create(), [...['size' => '1024x1024', 'quality' => 'medium'], ...$input])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown size' => [['size' => '2048x2048'], 'size'],
    'unknown quality' => [['quality' => 'ultra'], 'quality'],
    'five reference images' => [['reference_images' => array_map(fn ($i) => "https://example.com/{$i}.png", range(1, 5))], 'reference_images'],
    'not an http url' => [['reference_images' => ['ftp://example.com/a.png']], 'reference_images.0'],
    'the same url twice' => [['reference_images' => ['https://example.com/a.png', 'https://example.com/a.png']], 'reference_images.0'],
    'prompt too long' => [['prompt' => str_repeat('a', 4_001)], 'prompt'],
]);

it('counts a 4,000 character prompt as within the limit', function () {
    fakePricing();

    quoteAs(User::factory()->create(), ['prompt' => str_repeat('é', 4_000), 'size' => '1024x1024', 'quality' => 'low'])
        ->assertOk();
});

it('is only available to signed-in users', function () {
    quoteAs(null, ['size' => '1024x1024', 'quality' => 'low'])->assertUnauthorized();
});

it('says so when Pixelforge pricing cannot be loaded', function () {
    Http::fake(['pixelforge.test/v1/pricing' => Http::response('', 503)]);

    quoteAs(User::factory()->create(), ['size' => '1024x1024', 'quality' => 'low'])
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'nothing was charged'));
});
