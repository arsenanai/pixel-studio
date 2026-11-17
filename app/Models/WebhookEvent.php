<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $provider_event_id
 * @property string $type
 * @property string|null $generation_id
 * @property int|null $delivery_attempt
 * @property string $payload
 * @property CarbonImmutable $received_at
 * @property-read Generation|null $generation
 */
class WebhookEvent extends Model
{
    public const CREATED_AT = 'received_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'delivery_attempt' => 'integer',
            'received_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
