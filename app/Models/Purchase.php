<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Purchase extends Model
{
    protected $fillable = [
        'authorization_network_id',
        'card_id',
        'month',
        'status',
        'authorized_amount_cents',
        'captured_amount_cents',
        'reserved_amount_cents',
        'has_final_capture',
        'has_cancellation',
    ];

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return HasOne<Authorization, $this>
     */
    public function authorization(): HasOne
    {
        return $this->hasOne(Authorization::class);
    }

    /**
     * @return HasMany<Capture, $this>
     */
    public function captures(): HasMany
    {
        return $this->hasMany(Capture::class);
    }

    /**
     * @return HasOne<Cancellation, $this>
     */
    public function cancellation(): HasOne
    {
        return $this->hasOne(Cancellation::class);
    }

    /**
     * @return HasMany<PurchaseIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(PurchaseIssue::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    protected function casts(): array
    {
        return [
            'has_final_capture' => 'boolean',
            'has_cancellation' => 'boolean',
        ];
    }
}
