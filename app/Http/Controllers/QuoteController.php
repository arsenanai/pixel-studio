<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Generations\QuoteGeneration;
use App\Http\Requests\QuoteGenerationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class QuoteController
{
    public function __invoke(QuoteGenerationRequest $request, QuoteGeneration $quote, #[CurrentUser] User $user): JsonResponse
    {
        $price = $quote(
            $request->prompt(),
            $request->size(),
            $request->quality(),
            count($request->referenceImages()),
        );
        $available = $user->availableCredits();

        return response()->json([
            ...$price->jsonSerialize(),
            // Decided here, so the browser never compares money itself.
            'credits' => [
                'available' => $available,
                'sufficient' => ! $price->maxCharge->isGreaterThan($available),
            ],
        ]);
    }
}
