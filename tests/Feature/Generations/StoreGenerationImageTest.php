<?php

use App\Actions\Generations\ApplyGenerationUpdate;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

function succeedWithFreshImage(Generation $generation): Generation
{
    return app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration('generation-succeeded', [
        'id' => $generation->provider_job_id,
        'image_url_expires_at' => CarbonImmutable::now()->addDay()->toIso8601ZuluString(),
    ]));
}

it('copies a finished image into our own storage and serves it from there', function () {
    $user = User::factory()->create();
    $generation = succeedWithFreshImage(Generation::factory()->for($user)->processing()->create());

    $generation->refresh();
    expect($generation->image_path)->toBe("generations/{$generation->id}.png");
    Storage::disk('local')->assertExists($generation->image_path);

    $this->actingAs($user)->get('/history')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('generations.data.0.image.url', route('generations.image', $generation))
        ->where('generations.data.0.image.expiresAt', null));

    $response = $this->actingAs($user)->get(route('generations.image', $generation));
    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->streamedContent())->toBe(TestCase::PNG)
        ->and($response->headers->get('Cache-Control'))->toContain('private');
});

it('keeps each image private to its owner, and visible to operators', function () {
    $owner = User::factory()->create();
    $generation = succeedWithFreshImage(Generation::factory()->for($owner)->processing()->create());

    $this->actingAs(User::factory()->create())->get(route('generations.image', $generation))->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get(route('generations.image', $generation))->assertOk();
});

it('refuses to store anything that is not a PNG', function () {
    Http::swap(new HttpFactory);
    Http::preventStrayRequests();
    Http::fake(['pixelforge.promptedit.dev/images/*' => Http::response('<html>nope</html>', 200, ['Content-Type' => 'text/html'])]);
    Log::spy();

    $generation = succeedWithFreshImage(Generation::factory()->processing()->create());

    expect($generation->refresh()->image_path)->toBeNull();
    Log::shouldHaveReceived('warning')->with('pixelforge.image.not_stored', Mockery::any())->once();
});

it('does not try to fetch an image whose link has already expired', function () {
    $generation = Generation::factory()->succeeded(completedAt: CarbonImmutable::now()->subDays(2))->create();

    (new StoreGenerationImage($generation->id))->handle('local');

    expect($generation->refresh()->image_path)->toBeNull();
    Http::assertNothingSent();
});

it('backfills images that finished before copying existed', function () {
    Queue::fake();
    $fresh = Generation::factory()->succeeded()->create(['image_url_expires_at' => CarbonImmutable::now()->addHours(3)]);
    Generation::factory()->succeeded()->create(['image_url_expires_at' => CarbonImmutable::now()->subHour()]);
    Generation::factory()->succeeded()->create(['image_url_expires_at' => CarbonImmutable::now()->addHour(), 'image_path' => 'generations/done.png']);

    $this->artisan('pixelforge:store-images')->expectsOutputToContain('Queued 1 images')->assertSuccessful();

    Queue::assertPushed(StoreGenerationImage::class, fn (StoreGenerationImage $job) => $job->generationId === $fresh->id);
    Queue::assertPushed(StoreGenerationImage::class, 1);
});
