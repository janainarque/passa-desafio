<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('card_month_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->constrained();
            $table->string('month', 7);
            $table->bigInteger('limit_cents');
            $table->bigInteger('limit_remaining_cents');
            $table->timestamps();
            $table->unique(['card_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_month_balances');
    }
};
