<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Capture extends Model
{
    protected $fillable = [
        'network_id',
        'purchase_id',
        'sequence',
        'amount_cents',
        'currency',
        'final',
        'occurred_at',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    protected function casts(): array
    {
        return [
            'final' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
