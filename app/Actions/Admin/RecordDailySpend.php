<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecordDailySpend
{
    public function __invoke(Generation $generation): void
    {
        $charge = $generation->charge_nano;
        $cost = $generation->provider_cost_nano;

        if (! $generation->status->isTerminal() || $generation->completed_at === null || $charge === null || $cost === null) {
            throw new LogicException("Generation [{$generation->id}] has not finished, so it has no spend to record.");
        }

        $succeeded = $generation->status === GenerationStatus::Succeeded;

        DB::table('daily_spend')->upsert(
            [[
                'day' => $generation->completed_at->utc()->toDateString(),
                'user_id' => $generation->user_id,
                'succeeded' => $succeeded ? 1 : 0,
                'failed' => $succeeded ? 0 : 1,
                'charged_nano' => $charge->nano,
                'provider_cost_nano' => $cost->nano,
            ]],
            ['day', 'user_id'],
            [
                'succeeded' => DB::raw('daily_spend.succeeded + excluded.succeeded'),
                'failed' => DB::raw('daily_spend.failed + excluded.failed'),
                'charged_nano' => DB::raw('daily_spend.charged_nano + excluded.charged_nano'),
                'provider_cost_nano' => DB::raw('daily_spend.provider_cost_nano + excluded.provider_cost_nano'),
            ],
        );
    }
}
