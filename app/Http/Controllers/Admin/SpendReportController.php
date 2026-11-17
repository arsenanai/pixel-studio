<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildSpendReport;
use App\Actions\Admin\ReconcileUsage;
use App\Data\Reports\ReconciledDay;
use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Http\Requests\SpendReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Inertia\Inertia;
use Inertia\Response;

final class SpendReportController
{
    public function __invoke(
        SpendReportRequest $request,
        BuildSpendReport $report,
        ReconcileUsage $reconcile,
        #[Config('services.pixelforge.api_key')] string $apiKey,
    ): Response {
        $from = $request->from();
        $to = $request->to();
        $spend = $report($from, $to);

        return Inertia::render('Admin/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            // /v1/usage is per API key, so say which one these numbers are checked against.
            'source' => [
                'environment' => app()->environment(),
                'keyHint' => $apiKey === '' ? null : '…'.substr($apiKey, -4),
            ],
            'total' => $spend['total'],
            'days' => array_reverse(array_map(
                fn (string $date) => ['date' => $date, 'totals' => $spend['days'][$date]],
                array_keys($spend['days']),
            )),
            'users' => $spend['users'],
            'dayUsers' => array_reverse($spend['dayUsers']),
            // Loaded after the page renders, so a slow or down Pixelforge never blocks the report.
            'reconciliation' => Inertia::defer(fn () => $this->reconciliation($reconcile, $from, $to)),
        ]);
    }

    /** @return array<string, mixed> */
    private function reconciliation(ReconcileUsage $reconcile, CarbonImmutable $from, CarbonImmutable $to): array
    {
        try {
            $days = $reconcile($from, $to);
        } catch (PixelforgeRequestFailed|PixelforgeUnreachable|InvalidPixelforgePayload) {
            return ['available' => false, 'days' => [], 'mismatches' => 0];
        }

        $mismatches = array_values(array_filter($days, fn (ReconciledDay $day) => ! $day->matches()));

        return [
            'available' => true,
            'checkedDays' => count($days),
            'mismatches' => count($mismatches),
            'days' => $mismatches,
        ];
    }
}
