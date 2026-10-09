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
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->string('month', 7)->nullable();
            $table->string('reference', 64)->nullable();
            $table->string('type');
            $table->bigInteger('card_limit_delta_cents')->default(0);
            $table->bigInteger('company_balance_delta_cents')->default(0);
            $table->bigInteger('company_reserved_delta_cents')->default(0);
            $table->timestampTz('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
