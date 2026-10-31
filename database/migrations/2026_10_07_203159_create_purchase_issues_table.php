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
        Schema::create('purchase_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained();
            $table->string('code');
            $table->string('related_network_id', 64)->nullable();
            $table->timestampTz('detected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_issues');
    }
};
