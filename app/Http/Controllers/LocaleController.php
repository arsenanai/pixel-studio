<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['locale' => ['required', 'string', Rule::in(SetLocale::supported())]]);
        $locale = $request->string('locale')->toString();

        $request->session()->put(SetLocale::SESSION_KEY, $locale);

        $user = $request->user();

        if ($user instanceof User) {
            $user->locale = $locale;
            $user->save();
        }

        return back();
    }
}
