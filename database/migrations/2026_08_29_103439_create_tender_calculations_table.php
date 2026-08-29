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
        Schema::create('tender_calculations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->jsonb('input_values');
            $table->decimal('bid_price', total: 14, places: 2)->nullable();
            $table->decimal('unit_price', total: 14, places: 2)->nullable();
            $table->decimal('min_margin', total: 14, places: 2)->nullable();
            $table->decimal('target_margin', total: 14, places: 2)->nullable();
            $table->decimal('actual_margin', total: 14, places: 2)->nullable();
            $table->decimal('break_even', total: 14, places: 2)->nullable();
            $table->decimal('risk_surcharge', total: 14, places: 2)->nullable();
            $table->timestamps();

            $table->unique(['tender_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_calculations');
    }
};
