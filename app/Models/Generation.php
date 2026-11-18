<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\TokenRatesCast;
use App\Data\Pixelforge\GenerationRequest;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\ValueObjects\Money;
use App\ValueObjects\TokenRates;
use App\ValueObjects\TokenUsage;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<string> $reference_images
 * @property GenerationStatus $status
 * @property string|null $provider_job_id
 * @property TokenRates|null $rates
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property string|null $image_path
 * @property int|null $usage_input_text_tokens
 * @property int|null $usage_input_image_tokens
 * @property int|null $usage_output_tokens
 * @property int|null $usage_reasoning_tokens
 * @property Money $estimated_provider_cost_nano
 * @property Money $estimated_charge_nano
 * @property Money $estimated_min_charge_nano
 * @property Money $estimated_max_charge_nano
 * @property Money|null $provider_cost_nano
 * @property Money|null $charge_nano
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    public const METADATA_KEY = 'generation_id';

    protected $attributes = [
        'status' => 'queued',
        'submit_attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'status' => GenerationStatus::class,
            'rates' => TokenRatesCast::class,
            'image_url_expires_at' => 'immutable_datetime',
            'usage_input_text_tokens' => 'integer',
            'usage_input_image_tokens' => 'integer',
            'usage_output_tokens' => 'integer',
            'usage_reasoning_tokens' => 'integer',
            'estimated_provider_cost_nano' => MoneyCast::class,
            'estimated_charge_nano' => MoneyCast::class,
            'estimated_min_charge_nano' => MoneyCast::class,
            'estimated_max_charge_nano' => MoneyCast::class,
            'provider_cost_nano' => MoneyCast::class,
            'charge_nano' => MoneyCast::class,
            'submit_attempts' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing]);
    }

    public function usage(): ?TokenUsage
    {
        if ($this->usage_input_text_tokens === null
            || $this->usage_input_image_tokens === null
            || $this->usage_output_tokens === null
            || $this->usage_reasoning_tokens === null) {
            return null;
        }

        return new TokenUsage(
            $this->usage_input_text_tokens,
            $this->usage_input_image_tokens,
            $this->usage_output_tokens,
            $this->usage_reasoning_tokens,
        );
    }

    public function profit(): ?Money
    {
        if ($this->charge_nano === null || $this->provider_cost_nano === null) {
            return null;
        }

        return $this->charge_nano->subtract($this->provider_cost_nano);
    }

    public function toPixelforgeRequest(): GenerationRequest
    {
        return new GenerationRequest(
            idempotencyKey: $this->id,
            prompt: $this->prompt,
            size: $this->size,
            quality: $this->quality,
            referenceImages: $this->reference_images,
            metadata: [self::METADATA_KEY => $this->id],
        );
    }
}
