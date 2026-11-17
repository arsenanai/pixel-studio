<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Generations\QuoteGeneration;
use App\Http\Requests\QuoteGenerationRequest;
use Illuminate\Http\JsonResponse;

final class QuoteController
{
    public function __invoke(QuoteGenerationRequest $request, QuoteGeneration $quote): JsonResponse
    {
        return response()->json($quote(
            $request->prompt(),
            $request->size(),
            $request->quality(),
            count($request->referenceImages()),
        ));
    }
}
