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
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('name');
            $table->string('issuing_body')->nullable();
            $table->date('valid_from');
            $table->date('expiry_date');
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('notes')->nullable();
            // Reminder-tracking columns: written only by the expiry-check scheduled command
            // (forceFill, excluded from the model's #[Fillable(...)] list), same
            // one-directional "state only moves forward" shape as TenderDeadline's/Task's
            // escalation columns — see [[deadlines]].
            $table->unsignedTinyInteger('last_reminder_threshold_days')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
