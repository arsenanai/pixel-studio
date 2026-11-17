<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Models\Generation;
use App\Models\User;
use App\ValueObjects\Money;
use App\ValueObjects\TokenRates;
use App\ValueObjects\TokenUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Defaults describe a medium 1024x1024 generation with a ~40 character prompt,
 * quoted at the published rates. States keep status, usage and cost consistent.
 *
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prompt' => 'A lighthouse on a cliff at dawn, oil paint',
            'size' => ImageSize::Square,
            'quality' => ImageQuality::Medium,
            'reference_images' => [],
            'status' => GenerationStatus::Queued,
            // 10 text tokens (8-12), 1,100 output tokens, 200-2,000 reasoning tokens.
            'estimated_provider_cost_nano' => Money::fromNano(57_800_000),
            'estimated_charge_nano' => Money::fromNano(72_250_000),
            'estimated_min_charge_nano' => Money::fromNano(58_175_000),
            'estimated_max_charge_nano' => Money::fromNano(86_325_000),
        ];
    }

    public static function publishedRates(): TokenRates
    {
        return TokenRates::fromUsdStrings([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ]);
    }

    /** Accepted by Pixelforge: it has a provider id and a rate snapshot. */
    public function submitted(): static
    {
        return $this->state(fn () => [
            'provider_job_id' => 'gen_'.Str::ulid(),
            'rates' => self::publishedRates(),
            'submit_attempts' => 1,
            'submitted_at' => CarbonImmutable::now(),
        ]);
    }

    public function processing(): static
    {
        return $this->submitted()->state(fn () => [
            'status' => GenerationStatus::Processing,
            'started_at' => CarbonImmutable::now(),
        ]);
    }

    public function succeeded(?TokenUsage $usage = null, ?CarbonImmutable $completedAt = null): static
    {
        $usage ??= new TokenUsage(10, 0, 1_100, 1_100);
        $cost = self::publishedRates()->cost($usage);

        return $this->processing()->state(fn () => [
            'status' => GenerationStatus::Succeeded,
            'image_url' => 'https://pixelforge.promptedit.dev/images/example.png?signature=test',
            'image_url_expires_at' => ($completedAt ?? CarbonImmutable::now())->addDay(),
            'usage_input_text_tokens' => $usage->inputText,
            'usage_input_image_tokens' => $usage->inputImage,
            'usage_output_tokens' => $usage->output,
            'usage_reasoning_tokens' => $usage->reasoning,
            'provider_cost_nano' => $cost,
            'charge_nano' => $cost->withMarkup(2_500),
            'completed_at' => $completedAt ?? CarbonImmutable::now(),
        ]);
    }

    public function failed(string $code = 'generation_failed', string $message = 'The model could not produce an image for this prompt.'): static
    {
        return $this->processing()->state(fn () => [
            'status' => GenerationStatus::Failed,
            'provider_cost_nano' => Money::zero(),
            'charge_nano' => Money::zero(),
            'error_code' => $code,
            'error_message' => $message,
            'completed_at' => CarbonImmutable::now(),
        ]);
    }
}
