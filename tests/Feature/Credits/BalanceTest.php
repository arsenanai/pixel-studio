<?php

use App\Actions\Credits\PurchaseCredits;
use App\Actions\Generations\ApplyGenerationUpdate;
use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    fakePricing();
    Queue::fake();
});

function generateExpensive(): TestResponse
{
    return test()->post('/generations', [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ]);
}

function dollars(int $dollars): Money
{
    return Money::fromNano($dollars * Money::NANO_PER_DOLLAR);
}

it('adds credits with a simulated purchase', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/credits', ['dollars' => 5])->assertRedirect()->assertSessionHas('success');

    expect($user->creditBalance()->nano)->toBe(5_000_000_000)
        ->and(CreditTransaction::sole()->type)->toBe(CreditTransactionType::Purchase);
});

it('only sells the offered credit packs', function (mixed $dollars) {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/credits', ['dollars' => $dollars])->assertSessionHasErrors('dollars');

    expect($user->creditBalance()->isZero())->toBeTrue();
})->with([3, -5, 0, 'lots']);

it('blocks a generation the user cannot afford at its maximum price', function () {
    $this->actingAs(User::factory()->create());

    generateExpensive()->assertSessionHasErrors(['credits' => 'This image could cost up to $0.46734375, and you have $0.00 available. Buy credits to continue.']);

    expect(Generation::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('holds the maximum of unfinished generations, so credits cannot be spent twice', function () {
    $user = User::factory()->create();
    CreditTransaction::record($user->id, CreditTransactionType::Purchase, Money::fromNano(900_000_000));
    $this->actingAs($user);

    generateExpensive()->assertSessionHasNoErrors();

    expect($user->heldCredits()->nano)->toBe(467_343_750)
        ->and($user->availableCredits()->nano)->toBe(432_656_250);

    generateExpensive()->assertSessionHasErrors('credits');
    expect(Generation::count())->toBe(1);
});

it('debits exactly the actual charge, not the estimate, when a generation succeeds', function () {
    $user = User::factory()->withCredits(1)->create();
    $generation = Generation::factory()->for($user)->create();

    app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration('generation-succeeded'));

    $charge = CreditTransaction::query()->where('type', CreditTransactionType::Charge)->sole();
    expect($charge->amount_nano->nano)->toBe(-381_887_500)
        ->and($charge->generation_id)->toBe($generation->id)
        ->and($user->creditBalance()->nano)->toBe(618_112_500)
        ->and($user->heldCredits()->isZero())->toBeTrue()
        ->and($user->availableCredits()->nano)->toBe(618_112_500);
});

it('debits nothing for a failed generation and releases its hold', function () {
    $user = User::factory()->withCredits(1)->create();
    $generation = Generation::factory()->for($user)->create();
    expect($user->heldCredits()->isZero())->toBeFalse();

    app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration('generation-failed'));

    expect(CreditTransaction::count())->toBe(1)
        ->and($user->availableCredits()->equals(dollars(1)))->toBeTrue();
});

it('debits nothing when Pixelforge rejects the generation outright', function () {
    $user = User::factory()->withCredits(1)->create();

    app(ApplyGenerationUpdate::class)->failBeforeAcceptance(
        Generation::factory()->for($user)->create(), 'content_policy_violation', 'Declined.',
    );

    expect($user->availableCredits()->equals(dollars(1)))->toBeTrue();
});

it('does not debit twice for duplicate or contradicting success snapshots', function () {
    $user = User::factory()->withCredits(1)->create();
    $generation = Generation::factory()->for($user)->create();
    $apply = app(ApplyGenerationUpdate::class);

    $apply($generation, pixelforgeGeneration('generation-succeeded'));
    $apply($generation, pixelforgeGeneration('generation-succeeded', ['usage' => ['reasoning_tokens' => 8_000]]));
    $apply($generation, pixelforgeGeneration('generation-failed'));

    expect(CreditTransaction::query()->where('type', CreditTransactionType::Charge)->count())->toBe(1)
        ->and($user->creditBalance()->nano)->toBe(618_112_500);
});

it('refuses a second charge row for the same generation at the database level', function () {
    $generation = Generation::factory()->create();

    CreditTransaction::record($generation->user_id, CreditTransactionType::Charge, Money::fromNano(-1), $generation->id);
    CreditTransaction::record($generation->user_id, CreditTransactionType::Charge, Money::fromNano(-1), $generation->id);
})->throws(UniqueConstraintViolationException::class);

it('tells the generate page whether the quoted maximum is affordable', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/quote', ['size' => '1024x1024', 'quality' => 'low'])
        ->assertOk()
        ->assertJsonPath('credits.sufficient', false)
        ->assertJsonPath('credits.available.display', '$0.00');

    $this->post('/credits', ['dollars' => 1]);

    $this->postJson('/quote', ['size' => '1024x1024', 'quality' => 'low'])
        ->assertJsonPath('credits.sufficient', true)
        ->assertJsonPath('credits.available.display', '$1.00');
});

it('shows the balance, holds and history on the credits page', function () {
    $user = User::factory()->withCredits(5)->create();
    Generation::factory()->for($user)->processing()->create();

    $this->actingAs($user)->get('/credits')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Credits/Index')
        ->where('balance.display', '$5.00')
        ->where('held.display', '$0.086325')
        ->where('available.display', '$4.913675')
        ->has('packs', 3)
        ->where('transactions.data.0.label', 'Credits bought')
        ->where('transactions.data.0.balanceAfter.display', '$5.00')
        ->where('credits.available.display', '$4.913675'));
});

describe('statement', function () {
    it('shows a running balance after every entry, and the prompt behind each charge', function () {
        $user = User::factory()->withCredits(5)->create();
        $generation = Generation::factory()->for($user)->processing()->create(['prompt' => 'A lighthouse at dusk']);
        app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration('generation-succeeded', ['id' => $generation->provider_job_id]));

        $this->actingAs($user)->get('/credits')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transactions.total', 2)
            ->where('transactions.data.0.type', 'charge')
            ->where('transactions.data.0.prompt', 'A lighthouse at dusk')
            ->where('transactions.data.0.amount.display', '-$0.3818875')
            ->where('transactions.data.0.balanceAfter.display', '$4.6181125')
            ->where('transactions.data.1.type', 'purchase')
            ->where('transactions.data.1.balanceAfter.display', '$5.00'));
    });

    it('filters by type while keeping the true running balance', function () {
        $user = User::factory()->withCredits(5)->create();
        $generation = Generation::factory()->for($user)->processing()->create();
        app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration('generation-succeeded', ['id' => $generation->provider_job_id]));
        app(PurchaseCredits::class)($user, Money::fromUsdString('1'));

        $this->actingAs($user)->get('/credits?type=purchase')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.type', 'purchase')
            ->where('transactions.total', 2)
            ->where('transactions.data.0.balanceAfter.display', '$5.6181125')
            ->where('transactions.data.1.balanceAfter.display', '$5.00'));
    });

    it('pages through a long statement', function () {
        $user = User::factory()->create();

        foreach (range(1, 30) as $n) {
            app(PurchaseCredits::class)($user, Money::fromUsdString('1'));
        }

        $this->actingAs($user)->get('/credits?page=2')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('transactions.current_page', 2)
            ->has('transactions.data', 5)
            ->where('transactions.data.4.balanceAfter.display', '$1.00'));
    });
});
