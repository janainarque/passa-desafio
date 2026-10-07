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
        Schema::create('cards', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('card_token')->unique();
            $table->bigInteger('monthly_limit_cents');
            $table->bigInteger('purchase_limit_cents')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('blocked_mccs')->default('[]');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
