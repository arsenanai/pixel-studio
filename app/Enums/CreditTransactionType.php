<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case Charge = 'charge';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Credits bought',
            self::Charge => 'Image generated',
        };
    }
}
