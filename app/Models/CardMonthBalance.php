<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardMonthBalance extends Model
{
    protected $fillable = [
        'card_id',
        'month',
        'limit_cents',
        'limit_remaining_cents',
    ];

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
