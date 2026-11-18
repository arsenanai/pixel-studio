<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CloseAccount
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->name = __('Closed account');
            $user->email = "closed-{$user->id}@accounts.invalid";
            $user->password = Str::password(64);
            $user->remember_token = null;
            $user->locale = null;
            $user->save();
            $user->delete();

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }
}
