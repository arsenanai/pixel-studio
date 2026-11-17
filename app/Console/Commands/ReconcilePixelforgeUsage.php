<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\ReconcileUsage;
use App\Data\Reports\ReconciledDay;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ReconcilePixelforgeUsage extends Command
{
    protected $signature = 'pixelforge:reconcile
        {--from= : First UTC day, YYYY-MM-DD (default: 6 days before --to)}
        {--to= : Last UTC day, YYYY-MM-DD (default: today)}';

    protected $description = 'Compare recorded provider costs with GET /v1/usage; exits 1 on any difference';

    public function handle(ReconcileUsage $reconcile): int
    {
        $to = CarbonImmutable::parse($this->stringOption('to') ?? 'today', 'UTC');
        $from = CarbonImmutable::parse($this->stringOption('from') ?? $to->subDays(6)->toDateString(), 'UTC');

        $days = $reconcile($from, $to);

        $this->table(
            ['Day (UTC)', 'Ours', 'Pixelforge', 'Our cost', 'Pixelforge cost', 'Result'],
            array_map(fn (ReconciledDay $day) => [
                $day->date,
                $day->ourGenerations,
                $day->theirGenerations,
                $day->ourCost->format(),
                $day->theirCost->format(),
                $day->matches() ? 'match' : 'MISMATCH ('.$day->ourCost->subtract($day->theirCost)->format().')',
            ], $days),
        );

        $mismatched = array_filter($days, fn (ReconciledDay $day) => ! $day->matches());

        if ($mismatched !== []) {
            Log::error('pixelforge.reconcile.mismatch', ['days' => array_map(fn (ReconciledDay $day) => $day->date, array_values($mismatched))]);
            $this->error(count($mismatched).' day(s) differ from Pixelforge.');

            return self::FAILURE;
        }

        $this->info('Every day matches Pixelforge exactly.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
