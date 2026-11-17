<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use App\Exceptions\InvalidPixelforgePayload;
use App\ValueObjects\Money;

final readonly class PixelforgeUsage
{
    /** @param list<PixelforgeUsageDay> $days */
    public function __construct(
        public string $startDate,
        public string $endDate,
        public array $days,
        public int $totalGenerations,
        public Money $totalCost,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $total = $payload->object('total');

        return new self(
            startDate: $payload->string('start_date'),
            endDate: $payload->string('end_date'),
            days: array_map(PixelforgeUsageDay::fromPayload(...), $payload->objectList('data')),
            totalGenerations: $total->int('generations'),
            totalCost: $total->usd('cost_usd'),
        );
    }

    public function day(string $date): PixelforgeUsageDay
    {
        foreach ($this->days as $day) {
            if ($day->date === $date) {
                return $day;
            }
        }

        throw InvalidPixelforgePayload::at('$.data', "a row for {$date}");
    }
}
