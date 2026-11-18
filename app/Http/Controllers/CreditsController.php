<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Credits\CreditStatement;
use App\Actions\Credits\PurchaseCredits;
use App\Enums\CreditTransactionType;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CreditsController
{
    private const PER_PAGE = 25;

    /** @param list<int> $packs */
    public function index(Request $request, CreditStatement $statement, #[CurrentUser] User $user, #[Config('billing.credit_packs')] array $packs): Response
    {
        $request->validate(['type' => ['nullable', Rule::enum(CreditTransactionType::class)]]);
        $type = $request->enum('type', CreditTransactionType::class);

        return Inertia::render('Credits/Index', [
            'balance' => $user->creditBalance(),
            'held' => $user->heldCredits(),
            'available' => $user->availableCredits(),
            'packs' => array_map(fn (int $dollars) => [
                'dollars' => $dollars,
                'amount' => Money::fromNano($dollars * Money::NANO_PER_DOLLAR),
            ], $packs),
            'filters' => ['type' => $type?->value],
            'transactions' => $statement($user, $type, self::PER_PAGE),
        ]);
    }

    /** @param list<int> $packs */
    public function store(Request $request, PurchaseCredits $purchase, #[CurrentUser] User $user, #[Config('billing.credit_packs')] array $packs): RedirectResponse
    {
        $request->validate(['dollars' => ['required', 'integer', Rule::in($packs)]]);
        $amount = Money::fromNano($request->integer('dollars') * Money::NANO_PER_DOLLAR);

        $purchase($user, $amount);

        return back()->with('success', __('Added :amount of credits. No payment was taken; purchases here are simulated.', ['amount' => $amount->format()]));
    }
}
