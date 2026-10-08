<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Authorization extends Model
{
    protected $fillable = [
        'network_id',
        'purchase_id',
        'card_id',
        'card_token',
        'amount_cents',
        'currency',
        'mcc',
        'merchant',
        'decision',
        'reason',
        'occurred_at',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    protected function casts(): array
    {
        return [
            'merchant' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
