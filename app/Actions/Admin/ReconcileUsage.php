<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Reports\ReconciledDay;
use App\Data\Reports\SpendTotals;
use App\Services\Pixelforge\PixelforgeClient;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;

final readonly class ReconcileUsage
{
    public function __construct(
        private PixelforgeClient $client,
        private SpendReport $report,
    ) {}

    /** @return list<ReconciledDay> */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $usage = $this->client->usage($from, $to);
        $ours = $this->report->byDay($from, $to);

        $days = [];

        foreach ($usage->days as $theirs) {
            $local = $ours[$theirs->date] ?? SpendTotals::empty();
            unset($ours[$theirs->date]);

            $days[] = new ReconciledDay($theirs->date, $local->succeeded, $theirs->generations, $local->providerCost, $theirs->cost);
        }

        foreach ($ours as $date => $local) {
            $days[] = new ReconciledDay($date, $local->succeeded, 0, $local->providerCost, Money::zero());
        }

        return $days;
    }
}
