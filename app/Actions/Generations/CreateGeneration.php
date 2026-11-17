<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateGeneration
{
    public function __construct(private QuoteGeneration $quote) {}

    /** @param list<string> $referenceImages */
    public function __invoke(User $user, string $prompt, ImageSize $size, ImageQuality $quality, array $referenceImages): Generation
    {
        // Re-quoted on the server; the browser's numbers are never trusted.
        $quote = ($this->quote)($prompt, $size, $quality, count($referenceImages));

        return DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $quote): Generation {
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

            // Dispatched after commit, so a webhook can never arrive for a row that does not exist yet.
            SubmitGenerationJob::dispatch($generation);

            return $generation;
        });
    }
}
