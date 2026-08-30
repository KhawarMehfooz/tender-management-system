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
        Schema::create('tender_participation_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('distance_rating')->nullable();
            $table->unsignedTinyInteger('staffing_requirement_rating')->nullable();
            $table->unsignedTinyInteger('wage_qualification_rating')->nullable();
            $table->unsignedTinyInteger('reference_position_rating')->nullable();
            $table->unsignedTinyInteger('competitive_intensity_rating')->nullable();
            $table->unsignedTinyInteger('contractual_penalties_rating')->nullable();
            $table->unsignedTinyInteger('strategic_value_rating')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_participation_scores');
    }
};
