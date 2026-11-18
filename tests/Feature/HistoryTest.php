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

describe('filters', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        Generation::factory()->for($this->user)->succeeded()->create(['prompt' => 'A red fox in the snow']);
        Generation::factory()->for($this->user)->failed()->create(['prompt' => 'A paper boat at sea']);
        Generation::factory()->for($this->user)->processing()->create(['prompt' => 'A fox cub asleep']);
        Generation::factory()->create(['prompt' => 'Another user fox']);
    });

    it('counts each status for the tabs, for this user only', function () {
        $this->actingAs($this->user)->get('/history')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts', ['all' => 3, 'active' => 1, 'succeeded' => 1, 'failed' => 1])
            ->where('filters', ['status' => 'all', 'q' => '']));
    });

    it('shows one status at a time', function (string $status, string $prompt) {
        $this->actingAs($this->user)->get("/history?status={$status}")->assertInertia(fn (AssertableInertia $page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.prompt', $prompt)
            ->where('filters.status', $status));
    })->with([
        'in progress' => ['active', 'A fox cub asleep'],
        'done' => ['succeeded', 'A red fox in the snow'],
        'failed' => ['failed', 'A paper boat at sea'],
    ]);

    it('searches prompts without regard to case, inside the chosen status', function () {
        $this->actingAs($this->user)->get('/history?q=FOX')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('generations.data', 2));

        $this->actingAs($this->user)->get('/history?q=fox&status=succeeded')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.prompt', 'A red fox in the snow'));
    });

    it('keeps polling for unfinished work even when the filter hides it', function () {
        $this->actingAs($this->user)->get('/history?status=failed')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hasPending', true));
    });

    it('rejects an unknown status', function () {
        $this->actingAs($this->user)->get('/history?status=deleted')->assertSessionHasErrors('status');
    });

    it('keeps the filters in the page links', function () {
        Generation::factory()->for($this->user)->count(25)->failed()->create();

        $this->actingAs($this->user)->get('/history?status=failed')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generations.meta.total', 26)
            ->where('generations.links.next', fn (string $url) => str_contains($url, 'status=failed') && str_contains($url, 'page=2')));
    });
});
