<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Company extends Model
{
    protected $fillable = [
        'name',
        'balance_cents',
        'reserved_cents',
    ];

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /**
     * @return HasMany<CompanyDeposit, $this>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(CompanyDeposit::class);
    }
}
