<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Generations\CreateGeneration;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Exceptions\InsufficientCredits;
use App\Http\Requests\QuoteGenerationRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class GenerateController
{
    public function create(): Response
    {
        return Inertia::render('Generate/Index', [
            'sizes' => array_map(fn (ImageSize $size) => [
                'value' => $size->value,
                'label' => $size->label(),
                'hint' => $size->dimensions(),
            ], ImageSize::cases()),
            'qualities' => array_map(fn (ImageQuality $quality) => [
                'value' => $quality->value,
                'label' => $quality->label(),
            ], ImageQuality::cases()),
            'limits' => [
                'promptMaxCharacters' => QuoteGenerationRequest::PROMPT_MAX_CHARACTERS,
                'referenceImagesMax' => QuoteGenerationRequest::REFERENCE_IMAGES_MAX,
            ],
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $create, #[CurrentUser] User $user): RedirectResponse
    {
        try {
            $create($user, $request->prompt(), $request->size(), $request->quality(), $request->referenceImages());
        } catch (InsufficientCredits $insufficient) {
            return back()->withErrors(['credits' => $insufficient->getMessage()]);
        }

        return to_route('history')->with('success', 'Sent to Pixelforge. You’ll be charged the exact cost when it finishes; if it fails, it’s free.');
    }
}
