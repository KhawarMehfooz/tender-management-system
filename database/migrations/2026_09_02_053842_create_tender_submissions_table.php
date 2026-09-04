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
        Schema::create('tender_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('submission_date');
            $table->time('submission_time');
            $table->foreignUuid('responsible_employee_id')->constrained('users')->restrictOnDelete();
            $table->string('portal');
            $table->string('transmission_route');
            $table->boolean('receipt_confirmed')->default(false);
            $table->timestamp('receipt_confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_submissions');
    }
};
