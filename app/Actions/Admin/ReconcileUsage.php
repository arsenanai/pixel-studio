<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Reports\ReconciledDay;
use App\Data\Reports\SpendTotals;
use App\Services\Pixelforge\PixelforgeClient;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * Compares our recorded provider cost with Pixelforge's own billing, per UTC
 * day, as exact integers. Never against estimates or what users were charged.
 */
final readonly class ReconcileUsage
{
    public function __construct(
        private PixelforgeClient $client,
        private BuildSpendReport $report,
    ) {}

    /** @return list<ReconciledDay> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $usage = $this->client->usage($from, $to);
        $ours = ($this->report)($from, $to)['days'];

        $days = [];

        // Pixelforge lists every day in the range, including empty ones.
        foreach ($usage->days as $theirs) {
            $local = $ours[$theirs->date] ?? SpendTotals::empty();
            unset($ours[$theirs->date]);

            $days[] = new ReconciledDay($theirs->date, $local->succeeded, $theirs->generations, $local->providerCost, $theirs->cost);
        }

        // A day only we know about is a mismatch too.
        foreach ($ours as $date => $local) {
            $days[] = new ReconciledDay($date, $local->succeeded, 0, $local->providerCost, Money::zero());
        }

        return $days;
    }
}
