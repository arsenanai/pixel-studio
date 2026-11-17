<?php

declare(strict_types=1);

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\ValueObjects\Money;
use InvalidArgumentException;

final readonly class PurchaseCredits
{
    public function __invoke(User $user, Money $amount): CreditTransaction
    {
        if (! $amount->isGreaterThan(Money::zero())) {
            throw new InvalidArgumentException('A purchase must add credits.');
        }

        return CreditTransaction::record($user->id, CreditTransactionType::Purchase, $amount);
    }
}
