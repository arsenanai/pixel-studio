<?php

declare(strict_types=1);

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class CreditStatement
{
    /** @return LengthAwarePaginator<int, array{id: int, type: string, label: string, amount: Money, balanceAfter: Money, prompt: string|null, createdAt: string}> */
    public function __invoke(User $user, ?CreditTransactionType $type, int $perPage): LengthAwarePaginator
    {
        $ledger = DB::table('credit_transactions')
            ->where('user_id', $user->id)
            ->select('id', 'type', 'amount_nano', 'generation_id', 'created_at')
            ->selectRaw('SUM(amount_nano) OVER (ORDER BY created_at, id) as balance_after');

        $total = DB::table('credit_transactions')
            ->where('user_id', $user->id)
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type?->value))
            ->count();

        return DB::query()
            ->fromSub($ledger, 'ledger')
            ->leftJoin('generations', 'generations.id', '=', 'ledger.generation_id')
            ->when($type !== null, fn (Builder $query) => $query->where('ledger.type', $type?->value))
            ->select('ledger.*', 'generations.prompt')
            ->orderByDesc('ledger.created_at')
            ->orderByDesc('ledger.id')
            ->paginate($perPage, total: $total)
            ->withQueryString()
            ->through(fn (mixed $row) => $this->present($row));
    }

    /** @return array{id: int, type: string, label: string, amount: Money, balanceAfter: Money, prompt: string|null, createdAt: string} */
    private function present(mixed $row): array
    {
        if (! is_object($row) || ! is_string($row->type ?? null) || ! is_string($row->created_at ?? null) || ! is_numeric($row->id ?? null)) {
            throw new UnexpectedValueException('Unexpected row in the credit statement.');
        }

        $type = CreditTransactionType::from($row->type);
        $prompt = $row->prompt ?? null;

        return [
            'id' => (int) $row->id,
            'type' => $type->value,
            'label' => $type->label(),
            'amount' => Money::fromDatabase($row->amount_nano ?? null),
            'balanceAfter' => Money::fromDatabase($row->balance_after ?? null),
            'prompt' => is_string($prompt) ? $prompt : null,
            'createdAt' => CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601ZuluString(),
        ];
    }
}
