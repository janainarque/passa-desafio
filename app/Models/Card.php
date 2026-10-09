<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Card extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'card_token',
        'monthly_limit_cents',
        'purchase_limit_cents',
        'status',
        'blocked_mccs',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CardMonthBalance, $this>
     */
    public function monthBalances(): HasMany
    {
        return $this->hasMany(CardMonthBalance::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    protected function casts(): array
    {
        return [
            'blocked_mccs' => 'array',
        ];
    }
}
