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
        Schema::create('task_status_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->foreignUuid('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_status_changes');
    }
};
