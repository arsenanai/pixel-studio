<?php

use App\Models\Generation;
use App\Models\User;
use App\ValueObjects\TokenUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-17 12:00:00');
});

/** A /v1/usage response with the given nanodollar cost and count per day. */
function usageReport(string $from, string $to, array $days): array
{
    $rows = [];
    $total = 0;
    $count = 0;

    for ($day = CarbonImmutable::parse($from); $day->lessThanOrEqualTo(CarbonImmutable::parse($to)); $day = $day->addDay()) {
        [$generations, $nano] = $days[$day->toDateString()] ?? [0, 0];
        $rows[] = [
            'date' => $day->toDateString(),
            'generations' => $generations,
            'input_text_tokens' => 0,
            'input_image_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'cost_usd' => sprintf('%d.%09d', intdiv($nano, 1_000_000_000), $nano % 1_000_000_000),
        ];
        $total += $nano;
        $count += $generations;
    }

    return [
        'object' => 'usage',
        'start_date' => $from,
        'end_date' => $to,
        'data' => $rows,
        'total' => [
            'generations' => $count,
            'input_text_tokens' => 0,
            'input_image_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'cost_usd' => sprintf('%d.%09d', intdiv($total, 1_000_000_000), $total % 1_000_000_000),
        ],
    ];
}

function docsUsage(): TokenUsage
{
    return new TokenUsage(12, 765, 6_450, 3_184);
}

it('is only for operators', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    $this->post('/logout');
    $this->get('/admin')->assertRedirect('/login');
});

it('totals charges, provider cost and profit per UTC completion day and per user', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    // Created on the 15th, finished just after midnight UTC: it belongs to the 16th.
    Generation::factory()->for($alice)->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-16 00:00:05'))
        ->create(['created_at' => CarbonImmutable::parse('2026-11-15 23:59:50')]);
    Generation::factory()->for($alice)->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 09:00:00'))->create();
    Generation::factory()->for($bob)->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 10:00:00'))->create();
    Generation::factory()->for($bob)->failed()->create(['completed_at' => CarbonImmutable::parse('2026-11-17 11:00:00')]);
    Generation::factory()->for($bob)->processing()->create();

    Http::fake(['pixelforge.test/v1/usage*' => Http::response(usageReport('2026-10-19', '2026-11-17', []))]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Index')
            ->where('range', ['from' => '2026-10-19', 'to' => '2026-11-17'])
            ->where('total.succeeded', 3)
            ->where('total.failed', 1)
            ->where('total.charged.nano', '1145662500')
            ->where('total.providerCost.nano', '916530000')
            ->where('total.profit.nano', '229132500')
            ->where('total.margin', '20.00%')
            ->where('days.0.date', '2026-11-17')
            ->where('days.0.totals.providerCost.nano', '611020000')
            ->where('days.1.date', '2026-11-16')
            ->where('days.1.totals.providerCost.nano', '305510000')
            ->where('users.0.user.name', 'Alice')
            ->where('users.0.totals.charged.nano', '763775000')
            ->where('users.1.user.name', 'Bob')
            ->where('users.1.totals.failed', 1)
            ->has('dayUsers', 3)
            ->missing('reconciliation'));
});

it('leaves out generations that finished outside the range', function () {
    Generation::factory()->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-10 12:00:00'))->create();
    Http::fake(['*' => Http::response(usageReport('2026-11-11', '2026-11-17', []))]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin?from=2026-11-11&to=2026-11-17')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('total.succeeded', 0)->has('days', 0));
});

it('refuses a range longer than Pixelforge can report', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin?from=2026-01-01&to=2026-11-17')
        ->assertSessionHasErrors('from');
});

describe('reconciliation', function () {
    it('confirms when provider costs match Pixelforge exactly', function () {
        Generation::factory()->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 09:00:00'))->create();
        Http::fake(['pixelforge.test/v1/usage*' => Http::response(usageReport('2026-10-19', '2026-11-17', [
            '2026-11-17' => [1, 305_510_000],
        ]))]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('reconciliation.available', true)
                    ->where('reconciliation.checkedDays', 30)
                    ->where('reconciliation.mismatches', 0)));
    });

    it('shows a day that is off by a single nanodollar', function () {
        Generation::factory()->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 09:00:00'))->create();
        Http::fake(['pixelforge.test/v1/usage*' => Http::response(usageReport('2026-10-19', '2026-11-17', [
            '2026-11-17' => [1, 305_510_001],
        ]))]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('reconciliation.mismatches', 1)
                    ->where('reconciliation.days.0.date', '2026-11-17')
                    ->where('reconciliation.days.0.difference.nano', '-1')));
    });

    it('still renders the report when Pixelforge is unreachable', function () {
        Http::fake(['*' => Http::response('', 503)]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload->where('reconciliation.available', false)));
    });

    it('exits cleanly from the command when every day matches', function () {
        Generation::factory()->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-16 09:00:00'))->create();
        Http::fake(['*' => Http::response(usageReport('2026-11-10', '2026-11-16', ['2026-11-16' => [1, 305_510_000]]))]);

        $this->artisan('pixelforge:reconcile', ['--to' => '2026-11-16'])
            ->expectsOutputToContain('Every day matches')
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request['start_date'] === '2026-11-10' && $request['end_date'] === '2026-11-16');
    });

    it('exits non-zero from the command on any difference', function (array $theirs) {
        Generation::factory()->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-16 09:00:00'))->create();
        Http::fake(['*' => Http::response(usageReport('2026-11-10', '2026-11-16', $theirs))]);

        $this->artisan('pixelforge:reconcile', ['--to' => '2026-11-16'])
            ->expectsOutputToContain('MISMATCH')
            ->assertFailed();
    })->with([
        'cost differs' => [['2026-11-16' => [1, 305_509_999]]],
        'count differs' => [['2026-11-16' => [2, 305_510_000]]],
        'we recorded nothing they billed' => [['2026-11-15' => [1, 1_000]]],
        'they billed nothing we recorded' => [[]],
    ]);
});
