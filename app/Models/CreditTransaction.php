<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CreditTransactionType;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property CreditTransactionType $type
 * @property Money $amount_nano
 * @property string|null $generation_id
 * @property CarbonImmutable $created_at
 */
class CreditTransaction extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount_nano' => MoneyCast::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public static function record(int $userId, CreditTransactionType $type, Money $amount, ?string $generationId = null): self
    {
        $transaction = new self;
        $transaction->user_id = $userId;
        $transaction->type = $type;
        $transaction->amount_nano = $amount;
        $transaction->generation_id = $generationId;
        $transaction->save();

        return $transaction;
    }
}
