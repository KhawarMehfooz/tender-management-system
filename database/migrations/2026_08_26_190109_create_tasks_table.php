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
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('priority');
            $table->string('status')->default('open');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completion_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
