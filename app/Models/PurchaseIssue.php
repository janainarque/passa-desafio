<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseIssue extends Model
{
    protected $fillable = [
        'purchase_id',
        'code',
        'related_network_id',
        'detected_at',
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
            'detected_at' => 'datetime',
        ];
    }
}
