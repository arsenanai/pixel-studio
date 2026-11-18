<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Reports\SpendTotals;
use App\Enums\SpendSort;
use App\Support\Search;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use UnexpectedValueException;

final readonly class SpendReport
{
    public function total(CarbonImmutable $from, CarbonImmutable $to, ?int $userId = null): SpendTotals
    {
        $row = $this->totals($this->range($from, $to, $userId))->first();

        return $row === null ? SpendTotals::empty() : $this->toTotals($row);
    }

    /** @return array<string, SpendTotals> */
    public function byDay(CarbonImmutable $from, CarbonImmutable $to, ?int $userId = null): array
    {
        $rows = $this->totals($this->range($from, $to, $userId))
            ->addSelect('daily_spend.day')
            ->groupBy('daily_spend.day')
            ->get()
            ->keyBy(fn (object $row) => $this->date($row->day ?? null));

        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $days[$day->toDateString()] = $row === null ? SpendTotals::empty() : $this->toTotals($row);
        }

        return $days;
    }

    /** @return LengthAwarePaginator<int, array{user: array{id: int, name: string, email: string}, totals: SpendTotals}> */
    public function byUser(CarbonImmutable $from, CarbonImmutable $to, string $search, SpendSort $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->totals($this->range($from, $to))
            ->join('users', 'users.id', '=', 'daily_spend.user_id')
            ->addSelect('users.id as user_id', 'users.name', 'users.email')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByRaw($sort->expression().' DESC')
            ->orderBy('users.id');

        if ($search !== '') {
            Search::containsAny($query, ['users.name', 'users.email'], $search);
        }

        $page = $query->paginate($perPage)->withQueryString();

        return $page->through(fn (mixed $row) => [
            'user' => $this->user($this->object($row)),
            'totals' => $this->toTotals($this->object($row)),
        ]);
    }

    /** @return LazyCollection<int, array{date: string, user: array{id: int, name: string, email: string}, totals: SpendTotals}> */
    public function rows(CarbonImmutable $from, CarbonImmutable $to, ?int $userId = null): LazyCollection
    {
        return $this->range($from, $to, $userId)
            ->join('users', 'users.id', '=', 'daily_spend.user_id')
            ->select('daily_spend.*', 'users.name', 'users.email')
            ->orderBy('daily_spend.day')
            ->orderBy('daily_spend.user_id')
            ->cursor()
            ->map(fn (object $row) => [
                'date' => $this->date($row->day ?? null),
                'user' => $this->user($row),
                'totals' => new SpendTotals(
                    $this->integer($row->succeeded ?? null),
                    $this->integer($row->failed ?? null),
                    Money::fromNano($this->integer($row->charged_nano ?? null)),
                    Money::fromNano($this->integer($row->provider_cost_nano ?? null)),
                ),
            ]);
    }

    private function range(CarbonImmutable $from, CarbonImmutable $to, ?int $userId = null): Builder
    {
        return DB::table('daily_spend')
            ->whereBetween('daily_spend.day', [$from->toDateString(), $to->toDateString()])
            ->when($userId !== null, fn (Builder $query) => $query->where('daily_spend.user_id', $userId));
    }

    private function totals(Builder $query): Builder
    {
        return $query
            ->selectRaw('SUM(daily_spend.succeeded) as succeeded')
            ->selectRaw('SUM(daily_spend.failed) as failed')
            ->selectRaw('SUM(daily_spend.charged_nano) as charged')
            ->selectRaw('SUM(daily_spend.provider_cost_nano) as provider_cost');
    }

    private function toTotals(object $row): SpendTotals
    {
        return new SpendTotals(
            $this->integer($row->succeeded ?? 0),
            $this->integer($row->failed ?? 0),
            Money::fromNano($this->integer($row->charged ?? 0)),
            Money::fromNano($this->integer($row->provider_cost ?? 0)),
        );
    }

    /** @return array{id: int, name: string, email: string} */
    private function user(object $row): array
    {
        return [
            'id' => $this->integer($row->user_id ?? null),
            'name' => $this->text($row->name ?? null),
            'email' => $this->text($row->email ?? null),
        ];
    }

    private function object(mixed $row): object
    {
        if (! is_object($row)) {
            throw new UnexpectedValueException('Expected a row object from the spend query.');
        }

        return $row;
    }

    private function date(mixed $value): string
    {
        return substr($this->text($value), 0, 10);
    }

    private function integer(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

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
