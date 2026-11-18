<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GenerationImageController
{
    public function __invoke(Generation $generation, #[CurrentUser] User $user, #[Config('filesystems.images_disk')] ?string $disk): StreamedResponse
    {
        abort_if($disk === null, 404);

        abort_unless($generation->user_id === $user->id || $user->is_admin, 404);
        abort_if($generation->image_path === null || ! Storage::disk($disk)->exists($generation->image_path), 404);

        return Storage::disk($disk)->response($generation->image_path, "{$generation->id}.png", [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
