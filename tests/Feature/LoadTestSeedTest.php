<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-17 12:00:00');
});

it('seeds synthetic accounts whose rollup matches their generations', function () {
    $this->artisan('load-test:seed', ['--users' => 4, '--generations' => 300, '--days' => 3])->assertSuccessful();

    expect(User::query()->where('is_synthetic', true)->count())->toBe(4)
        ->and(Generation::query()->count())->toBe(300)
        ->and((int) DB::table('daily_spend')->sum('charged_nano'))
        ->toBe((int) Generation::query()->whereNotNull('completed_at')->sum('charge_nano'))
        ->and((int) DB::table('daily_spend')->sum('succeeded'))
        ->toBe(Generation::query()->where('status', GenerationStatus::Succeeded)->count());
});

it('asks before seeding production and leaves nothing for the poller there', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('load-test:seed', ['--users' => 2, '--generations' => 50, '--days' => 1])
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();
    expect(Generation::query()->count())->toBe(0);

    $this->artisan('load-test:seed', ['--users' => 2, '--generations' => 2000, '--days' => 1, '--force' => true])->assertSuccessful();
    expect(Generation::query()->count())->toBe(2000)
        ->and(Generation::query()->whereNull('completed_at')->count())->toBe(0);
});
