<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public const SESSION_KEY = 'locale';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    /** @return list<string> */
    public static function supported(): array
    {
        return array_keys(config()->array('app.locales'));
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();
        $candidates = [
            $user instanceof User ? $user->locale : null,
            $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $request->getPreferredLanguage(self::supported()),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::supported(), true)) {
                return $candidate;
            }
        }

        return config()->string('app.fallback_locale');
    }
}
