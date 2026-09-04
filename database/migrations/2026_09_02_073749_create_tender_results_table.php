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
        Schema::create('tender_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('winner')->nullable();
            $table->integer('our_rank')->nullable();
            $table->decimal('winning_price', 12, 2)->nullable();
            $table->decimal('our_price', 12, 2)->nullable();
            $table->decimal('price_gap', 12, 2)->nullable();
            $table->date('award_date')->nullable();
            $table->text('known_evaluation')->nullable();
            $table->text('reasoning')->nullable();
            $table->text('award_decision')->nullable();
            $table->jsonb('win_loss_reasons')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_results');
    }
};
