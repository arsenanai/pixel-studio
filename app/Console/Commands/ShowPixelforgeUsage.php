<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pixelforge\PixelforgeClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ShowPixelforgeUsage extends Command
{
    protected $signature = 'pixelforge:usage
        {--from= : First UTC day, YYYY-MM-DD (default: 6 days ago)}
        {--to= : Last UTC day, YYYY-MM-DD (default: today)}';

    protected $description = 'Show what Pixelforge billed this API key, per UTC day';

    public function handle(PixelforgeClient $client): int
    {
        $to = CarbonImmutable::parse($this->stringOption('to') ?? 'today', 'UTC');
        $from = CarbonImmutable::parse($this->stringOption('from') ?? $to->subDays(6)->toDateString(), 'UTC');

        $usage = $client->usage($from, $to);

        $rows = [];

        foreach ($usage->days as $day) {
            $rows[] = [$day->date, $day->generations, number_format($day->tokens->total()), $day->cost->format()];
        }

        $rows[] = ['Total', $usage->totalGenerations, '', $usage->totalCost->format()];

        $this->table(['Day (UTC)', 'Generations', 'Tokens', 'Cost'], $rows);

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
