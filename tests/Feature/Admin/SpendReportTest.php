<?php

use App\Actions\Account\CloseAccount;
use App\Models\Generation;
use App\Models\User;
use App\ValueObjects\TokenUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-17 12:00:00');
});

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
            ->has('days', 30)
            ->where('days.0.date', '2026-10-19')
            ->where('days.0.totals.succeeded', 0)
            ->where('days.28.date', '2026-11-16')
            ->where('days.28.totals.providerCost.nano', '305510000')
            ->where('days.29.date', '2026-11-17')
            ->where('days.29.totals.providerCost.nano', '611020000')
            ->where('users.data.0.user.name', 'Alice')
            ->where('users.data.0.totals.charged.nano', '763775000')
            ->where('users.data.1.user.name', 'Bob')
            ->where('users.data.1.totals.failed', 1)
            ->where('users.total', 2)
            ->missing('reconciliation'));
});

it('leaves out generations that finished outside the range', function () {
    Generation::factory()->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-10 12:00:00'))->create();
    Http::fake(['*' => Http::response(usageReport('2026-11-11', '2026-11-17', []))]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin?from=2026-11-11&to=2026-11-17')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('total.succeeded', 0)
            ->has('days', 7)
            ->where('days.0.totals.succeeded', 0)
            ->where('users.total', 0));
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

describe('filters', function () {
    beforeEach(function () {
        Http::fake(['*' => Http::response(usageReport('2026-10-19', '2026-11-17', []))]);
        $this->alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        Generation::factory()->for($this->alice)->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 09:00:00'))->create();
        Generation::factory()->for($this->bob)->count(2)->succeeded(new TokenUsage(12, 0, 1_000, 100), CarbonImmutable::parse('2026-11-16 09:00:00'))->create();
    });

    it('narrows every number to one user', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin?user={$this->alice->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.user.name', 'Alice')
                ->where('total.succeeded', 1)
                ->where('total.providerCost.nano', '305510000')
                ->where('days.28.totals.succeeded', 0)
                ->where('days.29.totals.succeeded', 1)
                ->where('users', null));
    });

    it('searches users by name or email', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin?q=BOB@')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('users.total', 1)
                ->where('users.data.0.user.name', 'Bob')
                ->where('total.succeeded', 3));
    });

    it('sorts users by the chosen measure', function (string $sort, string $first) {
        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin?sort={$sort}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('users.data.0.user.name', $first));
    })->with([
        'charged' => ['charged', 'Alice'],
        'profit' => ['profit', 'Alice'],
        'images' => ['images', 'Bob'],
    ]);

    it('rejects an unknown sort or user', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin?sort=random')->assertSessionHasErrors('sort');
        $this->actingAs($admin)->get('/admin?user=999999')->assertSessionHasErrors('user');
    });
});

describe('export', function () {
    it('streams one CSV row per user per day with exact decimal amounts', function () {
        $user = User::factory()->create(['name' => '=HYPERLINK("http://evil.test")', 'email' => 'eve@example.com']);
        Generation::factory()->for($user)->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 09:00:00'))->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/export?from=2026-11-11&to=2026-11-17');

        $response->assertOk()->assertDownload('spend-2026-11-11-to-2026-11-17.csv');
        $lines = explode("\n", trim($response->streamedContent()));

        expect($lines)->toHaveCount(2)
            ->and($lines[0])->toBe('date,user_id,name,email,images,failed,charged_usd,provider_cost_usd,profit_usd')
            ->and($lines[1])->toBe("2026-11-17,{$user->id},\"'=HYPERLINK(\"\"http://evil.test\"\")\",eve@example.com,1,0,0.3818875,0.30551,0.0763775");
    });

    it('is only for operators', function () {
        $this->actingAs(User::factory()->create())->get('/admin/export')->assertForbidden();
    });
});

it('filters to a closed account instead of silently showing everyone', function () {
    Http::fake(['*' => Http::response(usageReport('2026-10-19', '2026-11-17', []))]);
    $closed = User::factory()->create(['name' => 'Gone']);
    Generation::factory()->for($closed)->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 09:00:00'))->create();
    Generation::factory()->succeeded(docsUsage(), CarbonImmutable::parse('2026-11-17 10:00:00'))->create();
    app(CloseAccount::class)($closed);

    $this->actingAs(User::factory()->admin()->create())
        ->get("/admin?user={$closed->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.user.name', 'Closed account')
            ->where('total.succeeded', 1));

    $this->actingAs(User::factory()->admin()->create(['locale' => 'kk']))
        ->get('/admin')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('users.data', fn ($rows) => collect($rows)->pluck('user.name')->contains('Жабылған аккаунт')));

    expect(User::withTrashed()->find($closed->id)?->name)->toBe('Closed account');
});
