<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Credits\PurchaseCredits;
use App\Models\CreditTransaction;
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
    /** @param list<int> $packs */
    public function index(#[CurrentUser] User $user, #[Config('billing.credit_packs')] array $packs): Response
    {
        return Inertia::render('Credits/Index', [
            'balance' => $user->creditBalance(),
            'held' => $user->heldCredits(),
            'available' => $user->availableCredits(),
            'packs' => array_map(fn (int $dollars) => [
                'dollars' => $dollars,
                'amount' => Money::fromNano($dollars * Money::NANO_PER_DOLLAR),
            ], $packs),
            'transactions' => $user->creditTransactions()->latest()->latest('id')->limit(50)->get()
                ->map(fn (CreditTransaction $transaction) => [
                    'id' => $transaction->id,
                    'label' => $transaction->type->label(),
                    'amount' => $transaction->amount_nano,
                    'createdAt' => $transaction->created_at->toIso8601ZuluString(),
                ]),
        ]);
    }

    /** @param list<int> $packs */
    public function store(Request $request, PurchaseCredits $purchase, #[CurrentUser] User $user, #[Config('billing.credit_packs')] array $packs): RedirectResponse
    {
        $request->validate(['dollars' => ['required', 'integer', Rule::in($packs)]]);
        $amount = Money::fromNano($request->integer('dollars') * Money::NANO_PER_DOLLAR);

        $purchase($user, $amount);

        return back()->with('success', "Added {$amount->format()} of credits. No payment was taken; purchases here are simulated.");
    }
}
