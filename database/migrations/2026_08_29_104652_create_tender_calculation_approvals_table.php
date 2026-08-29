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
        Schema::create('tender_calculation_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_calculation_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('comment')->nullable();

            $table->unique(['tender_calculation_id', 'step']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_calculation_approvals');
    }
};
