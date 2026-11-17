<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\Money;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $attributes = [
        'is_admin' => false,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /** @return HasMany<Generation, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    /** @return HasMany<CreditTransaction, $this> */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function creditBalance(): Money
    {
        return Money::fromDatabase($this->creditTransactions()->toBase()->selectRaw('COALESCE(SUM(amount_nano), 0) as total')->value('total'));
    }

    public function heldCredits(): Money
    {
        return Money::fromDatabase($this->generations()->pending()->toBase()->selectRaw('COALESCE(SUM(estimated_max_charge_nano), 0) as total')->value('total'));
    }

    public function availableCredits(): Money
    {
        return $this->creditBalance()->subtract($this->heldCredits());
    }
}
