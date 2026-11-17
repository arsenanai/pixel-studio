<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\GenerationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class HistoryController
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $generations = $user->generations()->latest()->latest('id')->paginate(20);

        return Inertia::render('History/Index', [
            'generations' => GenerationResource::collection($generations),
            // The page polls only while something on it can still change.
            'hasPending' => $user->generations()->pending()->exists(),
        ]);
    }
}
