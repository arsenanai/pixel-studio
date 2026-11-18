<?php

declare(strict_types=1);

namespace App\Enums;

enum SpendSort: string
{
    case Charged = 'charged';
    case Profit = 'profit';
    case Images = 'images';

    public function expression(): string
    {
        return match ($this) {
            self::Charged => 'SUM(daily_spend.charged_nano)',
            self::Profit => 'SUM(daily_spend.charged_nano) - SUM(daily_spend.provider_cost_nano)',
            self::Images => 'SUM(daily_spend.succeeded)',
        };
    }
}
