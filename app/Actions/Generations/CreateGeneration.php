<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Exceptions\InsufficientCredits;
use App\Exceptions\TooManyPendingGenerations;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;

final readonly class CreateGeneration
{
    public function __construct(
        private QuoteGeneration $quote,
        #[Config('billing.max_pending_per_user')] private int $maxPending,
    ) {}

    /**
     * @param  list<string>  $referenceImages
     *
     * @throws InsufficientCredits
     * @throws TooManyPendingGenerations
     */
    public function __invoke(User $user, string $prompt, ImageSize $size, ImageQuality $quality, array $referenceImages): Generation
    {
        $quote = ($this->quote)($prompt, $size, $quality, count($referenceImages));

        return DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $quote): Generation {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->generations()->pending()->count() >= $this->maxPending) {
                throw new TooManyPendingGenerations($this->maxPending);
            }

            $available = $locked->availableCredits();

            if ($quote->maxCharge->isGreaterThan($available)) {
                throw new InsufficientCredits($quote->maxCharge, $available);
            }

            $generation = new Generation;
            $generation->user_id = $user->id;
            $generation->prompt = $prompt;
            $generation->size = $size;
            $generation->quality = $quality;
            $generation->reference_images = $referenceImages;
            $generation->estimated_provider_cost_nano = $quote->typicalProviderCost;
            $generation->estimated_charge_nano = $quote->typicalCharge;
            $generation->estimated_min_charge_nano = $quote->minCharge;
            $generation->estimated_max_charge_nano = $quote->maxCharge;
            $generation->save();

            SubmitGenerationJob::dispatch($generation);

            return $generation;
        });
    }
}
