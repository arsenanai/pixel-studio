<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class RebuildDailySpend
{
    public function __invoke(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): int
    {
        return DB::transaction(function () use ($from, $to): int {
            $this->scope(DB::table('daily_spend'), 'day', $from, $to, dates: true)->delete();

            $source = $this->scope(DB::table('generations'), 'completed_at', $from, $to, dates: false)
                ->whereIn('status', [GenerationStatus::Succeeded->value, GenerationStatus::Failed->value])
                ->selectRaw('DATE(completed_at) as day, user_id')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as succeeded', [GenerationStatus::Succeeded->value])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [GenerationStatus::Failed->value])
                ->selectRaw('COALESCE(SUM(charge_nano), 0) as charged_nano')
                ->selectRaw('COALESCE(SUM(provider_cost_nano), 0) as provider_cost_nano')
                ->groupByRaw('DATE(completed_at), user_id');

            return DB::table('daily_spend')->insertUsing(
                ['day', 'user_id', 'succeeded', 'failed', 'charged_nano', 'provider_cost_nano'],
                $source,
            );
        });
    }

    private function scope(Builder $query, string $column, ?CarbonImmutable $from, ?CarbonImmutable $to, bool $dates): Builder
    {
        if ($from !== null) {
            $query->where($column, '>=', $dates ? $from->toDateString() : $from->utc()->startOfDay());
        }

        if ($to !== null) {
            $dates
                ? $query->where($column, '<=', $to->toDateString())
                : $query->where($column, '<', $to->utc()->addDay()->startOfDay());
        }

        return $query;
    }
}
