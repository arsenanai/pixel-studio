<?php

use App\Models\Generation;
use App\Models\User;
use App\ValueObjects\TokenUsage;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

function history(User $user): AssertableInertia
{
    $page = null;

    test()->actingAs($user)->get('/history')->assertOk()->assertInertia(function (AssertableInertia $inertia) use (&$page) {
        $page = $inertia->component('History/Index');
    });

    return $page;
}

it('shows a user only their own generations, newest first', function () {
    $user = User::factory()->create();
    $older = Generation::factory()->for($user)->create(['created_at' => now()->subHour()]);
    $newer = Generation::factory()->for($user)->create();
    Generation::factory()->create();

    history($user)
        ->has('generations.data', 2)
        ->where('generations.data.0.id', $newer->id)
        ->where('generations.data.1.id', $older->id);
});

it('shows the estimate and the exact charge of a finished generation', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->succeeded(new TokenUsage(12, 765, 6_450, 3_184))->create();

    history($user)
        ->where('generations.data.0.status', 'succeeded')
        ->where('generations.data.0.charge', ['nano' => '381887500', 'display' => '$0.3818875', 'cents' => '$0.38'])
        ->where('generations.data.0.estimate.max.display', '$0.086325')
        ->where('hasPending', false)
        ->missing('generations.data.0.providerCost');
});

it('shows a failed generation as free, with the reason', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->failed('content_policy_violation', 'Pixelforge declined this prompt under its content policy.')->create();

    history($user)
        ->where('generations.data.0.status', 'failed')
        ->where('generations.data.0.charge.nano', '0')
        ->where('generations.data.0.error', 'Pixelforge declined this prompt under its content policy.');
});

it('stops offering an image once its signed URL has expired', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->succeeded(completedAt: CarbonImmutable::now()->subDays(2))->create();

    history($user)
        ->where('generations.data.0.image.url', null)
        ->has('generations.data.0.image.expiresAt');
});

it('tells the page to keep polling while anything is unfinished', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->processing()->create();

    history($user)
        ->where('hasPending', true)
        ->where('generations.data.0.statusLabel', 'Generating');
});

it('says when a submit is waiting on Pixelforge', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create(['submit_attempts' => 3]);

    history($user)->where('generations.data.0.statusLabel', 'Waiting for Pixelforge');
});

it('pages through long histories', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->count(25)->create();

    history($user)
        ->has('generations.data', 20)
        ->where('generations.meta.total', 25);
});

it('sends guests to sign in', function () {
    $this->get('/history')->assertRedirect('/login');
});
