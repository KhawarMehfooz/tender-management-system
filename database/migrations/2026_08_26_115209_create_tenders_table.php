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
        Schema::create('tenders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('internal_id')->unique();
            $table->string('title');
            $table->string('procurement_number')->nullable();
            $table->string('contracting_authority');
            $table->string('procurement_office')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('city')->nullable();
            $table->foreignUuid('nuts_code_id')->nullable()->constrained('nuts_codes')->nullOnDelete();
            $table->foreignUuid('service_category_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('sector_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('procurement_procedure_id')->constrained()->restrictOnDelete();
            $table->decimal('estimated_contract_volume', 12, 2)->nullable();
            $table->boolean('estimated_contract_volume_unknown')->default(false);
            $table->string('contract_term')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->text('extension_options')->nullable();
            $table->dateTime('submission_deadline');
            $table->dateTime('bidder_question_deadline')->nullable();
            $table->dateTime('site_visit_date')->nullable();
            $table->unsignedSmallInteger('bid_validity_days')->nullable();
            $table->date('publication_date')->nullable();
            $table->foreignUuid('source_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('cpv_code_id')->nullable()->constrained('cpv_codes')->nullOnDelete();
            $table->string('portal_link')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('intake');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
