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
        Schema::create('tender_site_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->text('attendees');
            $table->string('contact_person')->nullable();
            $table->text('access_routes')->nullable();
            $table->text('parking')->nullable();
            $table->text('areas')->nullable();
            $table->text('risks')->nullable();
            $table->text('technical_particularities')->nullable();
            $table->text('staffing_requirement')->nullable();
            $table->text('competitors_spotted')->nullable();
            $table->text('open_questions')->nullable();
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
        Schema::dropIfExists('tender_site_visits');
    }
};
