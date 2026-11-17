<?php

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    fakePricing();
    Queue::fake();
});

function submitForm(array $input = []): TestResponse
{
    return test()->post('/generations', [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
        ...$input,
    ]);
}

it('stores a queued generation with its estimate and queues the submit', function () {
    $user = User::factory()->withCredits()->create();

    $this->actingAs($user);
    submitForm()->assertRedirect('/history')->assertSessionHas('success');

    $generation = Generation::sole();

    expect($generation->user_id)->toBe($user->id)
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->size)->toBe(ImageSize::Landscape)
        ->and($generation->quality)->toBe(ImageQuality::High)
        ->and($generation->reference_images)->toBe(['https://example.com/a.png', 'https://example.com/b.png'])
        ->and($generation->estimated_min_charge_nano->nano)->toBe(357_781_250)
        ->and($generation->estimated_charge_nano->nano)->toBe(412_562_500)
        ->and($generation->estimated_max_charge_nano->nano)->toBe(467_343_750)
        ->and($generation->estimated_provider_cost_nano->nano)->toBe(330_050_000)
        ->and($generation->charge_nano)->toBeNull();

    Queue::assertPushed(SubmitGenerationJob::class, fn (SubmitGenerationJob $job) => $job->generation->is($generation));
});

it('prices the generation itself instead of trusting the browser', function () {
    $this->actingAs(User::factory()->withCredits()->create());

    submitForm(['estimated_charge_nano' => 1, 'charge_nano' => 1, 'status' => 'succeeded']);

    expect(Generation::sole()->estimated_charge_nano->nano)->toBe(412_562_500)
        ->and(Generation::sole()->status)->toBe(GenerationStatus::Queued);
});

it('does not call Pixelforge while handling the form', function () {
    $this->actingAs(User::factory()->withCredits()->create());

    submitForm();

    Http::assertSentCount(1);
});

it('requires a prompt', function () {
    $this->actingAs(User::factory()->create());

    submitForm(['prompt' => ''])->assertSessionHasErrors(['prompt' => 'Describe the image you want.']);

    expect(Generation::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects a reference image that is not a URL', function () {
    $this->actingAs(User::factory()->create());

    submitForm(['reference_images' => ['not a url']])->assertSessionHasErrors('reference_images.0');
});

it('sends guests to sign in', function () {
    submitForm()->assertRedirect('/login');
});

it('shows the generate form with every size and quality Pixelforge offers', function () {
    $this->actingAs(User::factory()->create())
        ->get('/generate')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Generate/Index')
            ->has('sizes', 3)
            ->where('sizes.1', ['value' => '1536x1024', 'label' => 'Landscape', 'hint' => '1536×1024'])
            ->has('qualities', 3)
            ->where('limits.promptMaxCharacters', 4_000)
            ->where('auth.user.isAdmin', false)
            ->missing('auth.user.password'));
});
