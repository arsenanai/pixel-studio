<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ReconcileUsage;
use App\Actions\Admin\SpendReport;
use App\Data\Reports\ReconciledDay;
use App\Data\Reports\SpendTotals;
use App\Exceptions\InvalidPixelforgePayload;
use App\Exceptions\PixelforgeRequestFailed;
use App\Exceptions\PixelforgeUnreachable;
use App\Http\Requests\SpendReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SpendReportController
{
    private const USERS_PER_PAGE = 25;

    public function index(
        SpendReportRequest $request,
        SpendReport $report,
        ReconcileUsage $reconcile,
        #[Config('services.pixelforge.api_key')] string $apiKey,
    ): Response {
        $from = $request->from();
        $to = $request->to();
        $user = $request->filteredUser();
        $days = $report->byDay($from, $to, $user?->id);

        return Inertia::render('Admin/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => [
                'q' => $request->search(),
                'sort' => $request->sort()->value,
                'user' => $user === null ? null : ['id' => $user->id, 'name' => $user->trashed() ? __('Closed account') : $user->name, 'email' => $user->email],
            ],
            'source' => [
                'environment' => app()->environment(),
                'keyHint' => $apiKey === '' ? null : '...'.substr($apiKey, -4),
            ],
            'total' => $report->total($from, $to, $user?->id),
            'days' => array_map(
                fn (string $date, SpendTotals $totals) => ['date' => $date, 'totals' => $totals],
                array_keys($days),
                $days,
            ),
            'users' => $user === null
                ? $report->byUser($from, $to, $request->search(), $request->sort(), self::USERS_PER_PAGE)
                : null,
            'reconciliation' => $user === null ? Inertia::defer(fn () => $this->reconciliation($reconcile, $from, $to)) : null,
        ]);
    }

    public function export(SpendReportRequest $request, SpendReport $report): StreamedResponse
    {
        $from = $request->from();
        $to = $request->to();
        $user = $request->filteredUser();
        $name = "spend-{$from->toDateString()}-to-{$to->toDateString()}".($user === null ? '' : "-user-{$user->id}").'.csv';

        return response()->streamDownload(function () use ($report, $from, $to, $user): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['date', 'user_id', 'name', 'email', 'images', 'failed', 'charged_usd', 'provider_cost_usd', 'profit_usd'], escape: '');

            foreach ($report->rows($from, $to, $user?->id) as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['user']['id'],
                    $this->cell($row['user']['name']),
                    $this->cell($row['user']['email']),
                    $row['totals']->succeeded,
                    $row['totals']->failed,
                    $row['totals']->charged->toDecimalString(),
                    $row['totals']->providerCost->toDecimalString(),
                    $row['totals']->profit()->toDecimalString(),
                ], escape: '');
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
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
