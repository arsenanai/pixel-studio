<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser('Demo User', 'demo', admin: false);
        $this->seedUser('Operator', 'admin', admin: true);
    }

    private function seedUser(string $name, string $account, bool $admin): void
    {
        $email = Config::string("seed.{$account}.email");
        $configured = config("seed.{$account}.password");
        $password = is_string($configured) && $configured !== '' ? $configured : Str::password(20);

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->email_verified_at ??= now();
        $user->is_admin = $admin;
        $user->save();

        $this->command->info($password === $configured ? "{$email} (password from config)" : "{$email} / {$password}");
    }
}
