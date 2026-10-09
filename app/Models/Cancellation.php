<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Cancellation extends Model
{
    protected $fillable = [
        'network_id',
        'purchase_id',
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
            'occurred_at' => 'datetime',
        ];
    }
}
