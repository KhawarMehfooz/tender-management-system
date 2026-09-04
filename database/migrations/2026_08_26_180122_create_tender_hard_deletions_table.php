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
        Schema::create('tender_hard_deletions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tender_id');
            $table->string('internal_id');
            $table->string('title');
            $table->foreignUuid('deleted_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_hard_deletions');
    }
};
