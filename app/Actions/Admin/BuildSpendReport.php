<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Reports\SpendTotals;
use App\Enums\GenerationStatus;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class BuildSpendReport
{
    /**
     * @return array{
     *     days: array<string, SpendTotals>,
     *     users: list<array{user: array{id: int, name: string, email: string}, totals: SpendTotals}>,
     *     dayUsers: list<array{date: string, user: array{id: int, name: string, email: string}, totals: SpendTotals}>,
     *     total: SpendTotals,
     * }
     */
    public function __invoke(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->finished($from, $to)
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->selectRaw('DATE(generations.completed_at) as day, users.id as user_id, users.name, users.email')
            ->selectRaw('SUM(CASE WHEN generations.status = ? THEN 1 ELSE 0 END) as succeeded', [GenerationStatus::Succeeded->value])
            ->selectRaw('SUM(CASE WHEN generations.status = ? THEN 1 ELSE 0 END) as failed', [GenerationStatus::Failed->value])
            ->selectRaw('COALESCE(SUM(generations.charge_nano), 0) as charged')
            ->selectRaw('COALESCE(SUM(generations.provider_cost_nano), 0) as provider_cost')
            ->groupByRaw('DATE(generations.completed_at), users.id, users.name, users.email')
            ->orderBy('day')
            ->orderBy('users.name')
            ->get();

        $days = [];
        $users = [];
        $dayUsers = [];
        $total = SpendTotals::empty();

        foreach ($rows as $row) {
            $date = $this->text($row->day);
            $user = ['id' => $this->integer($row->user_id), 'name' => $this->text($row->name), 'email' => $this->text($row->email)];
            $totals = new SpendTotals(
                $this->integer($row->succeeded),
                $this->integer($row->failed),
                Money::fromNano($this->integer($row->charged)),
                Money::fromNano($this->integer($row->provider_cost)),
            );

            $dayUsers[] = ['date' => $date, 'user' => $user, 'totals' => $totals];
            $days[$date] = ($days[$date] ?? SpendTotals::empty())->add($totals);
            $users[$user['id']] = [
                'user' => $user,
                'totals' => ($users[$user['id']]['totals'] ?? SpendTotals::empty())->add($totals),
            ];
            $total = $total->add($totals);
        }

        usort($users, fn (array $a, array $b) => $b['totals']->charged->nano <=> $a['totals']->charged->nano);

        return ['days' => $days, 'users' => $users, 'dayUsers' => $dayUsers, 'total' => $total];
    }

    private function finished(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('generations')
            ->whereIn('generations.status', [GenerationStatus::Succeeded->value, GenerationStatus::Failed->value])
            ->where('generations.completed_at', '>=', $from->utc()->startOfDay())
            ->where('generations.completed_at', '<', $to->utc()->addDay()->startOfDay());
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new UnexpectedValueException('Expected an integer from the spend query.');
    }

    private function text(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException('Expected a string from the spend query.');
        }

        return $value;
    }
}
