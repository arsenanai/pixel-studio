<?php

use App\Actions\Admin\RebuildDailySpend;
use App\Actions\Generations\ApplyGenerationUpdate;
use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function dailySpendRows(): array
{
    return DB::table('daily_spend')->orderBy('day')->orderBy('user_id')->get()
        ->map(fn (object $row) => array_map(fn (mixed $value) => (string) $value, (array) $row))
        ->all();
}

function applySnapshot(Generation $generation, string $fixture, array $overrides = []): void
{
    app(ApplyGenerationUpdate::class)($generation, pixelforgeGeneration($fixture, $overrides));
}

it('keeps the rollup equal to a rebuild from raw generations, through duplicates and out-of-order updates', function () {
    $apply = app(ApplyGenerationUpdate::class);
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $first = Generation::factory()->for($alice)->create();
    applySnapshot($first, 'generation-processing', ['id' => 'gen_a']);
    applySnapshot($first, 'generation-succeeded', ['id' => 'gen_a']);
    applySnapshot($first, 'generation-succeeded', ['id' => 'gen_a']);
    applySnapshot($first, 'generation-processing', ['id' => 'gen_a']);

    $second = Generation::factory()->for($alice)->create();
    applySnapshot($second, 'generation-succeeded', ['id' => 'gen_b', 'completed_at' => '2026-11-05T00:00:01Z']);

    $third = Generation::factory()->for($bob)->create();
    applySnapshot($third, 'generation-failed', ['id' => 'gen_c']);
    applySnapshot($third, 'generation-succeeded', ['id' => 'gen_c']);

    $apply->failBeforeAcceptance(Generation::factory()->for($bob)->create(), 'content_policy_violation', 'Blocked.');

    $incremental = dailySpendRows();

    expect($incremental)->toHaveCount(4)
        ->and(collect($incremental)->sum(fn (array $row) => (int) $row['succeeded']))->toBe(2)
        ->and(collect($incremental)->sum(fn (array $row) => (int) $row['failed']))->toBe(2);

    app(RebuildDailySpend::class)();

    expect(dailySpendRows())->toBe($incremental);
});

it('rebuilds only the requested days', function () {
    Generation::factory()->succeeded(completedAt: CarbonImmutable::parse('2026-11-10 12:00:00'))->create();
    $later = Generation::factory()->succeeded(completedAt: CarbonImmutable::parse('2026-11-12 12:00:00'))->create();
    DB::table('daily_spend')->update(['charged_nano' => 1]);

    app(RebuildDailySpend::class)(CarbonImmutable::parse('2026-11-12'), CarbonImmutable::parse('2026-11-12'));

    expect(DB::table('daily_spend')->orderBy('day')->pluck('charged_nano')->map(fn (mixed $v) => (int) $v)->all())
        ->toBe([1, $later->charge_nano?->nano]);
});
