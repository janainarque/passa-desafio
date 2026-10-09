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
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('authorization_network_id', 64)->unique();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->string('month', 7)->nullable();
            $table->string('status')->default('pending_authorization');
            $table->bigInteger('authorized_amount_cents')->nullable();
            $table->bigInteger('captured_amount_cents')->default(0);
            $table->bigInteger('reserved_amount_cents')->default(0);
            $table->boolean('has_final_capture')->default(false);
            $table->boolean('has_cancellation')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
