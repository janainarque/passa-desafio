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
        Schema::create('authorizations', function (Blueprint $table): void {
            $table->id();
            $table->string('network_id', 64)->unique();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->text('card_token');
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('mcc', 4);
            $table->jsonb('merchant');
            $table->string('decision');
            $table->string('reason')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorizations');
    }
};
