<?php

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
        Schema::create('tender_bid_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->string('decision');
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->foreignUuid('decided_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_bid_decisions');
    }
};
