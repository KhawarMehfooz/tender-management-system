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
        Schema::create('tender_follow_ups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('presentation_scheduled_at')->nullable();
            $table->text('presentation_notes')->nullable();
            $table->text('negotiation_notes')->nullable();
            $table->date('bid_validity_until')->nullable();
            $table->date('expected_result_date')->nullable();
            $table->text('expected_result_notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_follow_ups');
    }
};
