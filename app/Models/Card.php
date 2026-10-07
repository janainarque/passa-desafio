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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monthBalances(): HasMany
    {
        return $this->hasMany(CardMonthBalance::class);
    }

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
