<?php

declare(strict_types=1);

namespace App\Data\Pixelforge;

use Carbon\CarbonImmutable;

/** A webhook event. `generation` is null for events that carry none, such as `ping`. */
final readonly class PixelforgeEvent
{
    public const PING = 'ping';

    public function __construct(
        public string $id,
        public string $type,
        public CarbonImmutable $createdAt,
        public ?PixelforgeGeneration $generation,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $object = $payload->has('data') ? $payload->object('data')->nullableObject('object') : null;

        return new self(
            id: $payload->string('id'),
            type: $payload->string('type'),
            createdAt: $payload->timestamp('created_at'),
            generation: $object !== null && $object->nullableString('object') === 'generation'
                ? PixelforgeGeneration::fromPayload($object)
                : null,
        );
    }
}
