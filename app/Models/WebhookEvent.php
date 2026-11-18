<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
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
    use MassPrunable;

    public const KEEP_DAYS = 30;

    public const CREATED_AT = 'received_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'delivery_attempt' => 'integer',
            'received_at' => 'immutable_datetime',
        ];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('received_at', '<', CarbonImmutable::now()->subDays(self::KEEP_DAYS));
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
