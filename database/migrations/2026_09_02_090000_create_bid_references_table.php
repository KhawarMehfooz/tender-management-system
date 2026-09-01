<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Table is named `bid_references`, not `references` — REFERENCES is a reserved SQL
     * keyword (used in foreign-key constraint syntax) and Postgres would require quoting it
     * everywhere. The model stays named `Reference` and maps to this table explicitly.
     */
    public function up(): void
    {
        Schema::create('bid_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client');
            $table->foreignUuid('service_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('sector_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('contract_volume', 15, 2)->nullable();
            $table->boolean('contract_volume_unknown')->default(false);
            $table->unsignedInteger('headcount')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_references');
    }
};
