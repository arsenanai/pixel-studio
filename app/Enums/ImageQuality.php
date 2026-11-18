<?php

declare(strict_types=1);

namespace App\Enums;

enum ImageQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Low => __('Quick drafts'),
            self::Medium => __('Balanced'),
            self::High => __('Finest detail'),
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
        };
    }
}
