<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationStatus;
use App\ValueObjects\Money;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property bool $is_admin
 * @property string|null $locale
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $attributes = [
        'is_admin' => false,
        'locale' => null,
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
        $pending = array_map(fn (GenerationStatus $status) => $status->value, [GenerationStatus::Queued, GenerationStatus::Processing]);

        $row = DB::selectOne(
            'SELECT (SELECT COALESCE(SUM(amount_nano), 0) FROM credit_transactions WHERE user_id = ?)'
            .' - (SELECT COALESCE(SUM(estimated_max_charge_nano), 0) FROM generations WHERE user_id = ? AND status IN (?, ?)) AS available',
            [$this->id, $this->id, ...$pending],
        );

        return Money::fromDatabase(is_object($row) ? ($row->available ?? null) : null);
    }
}
