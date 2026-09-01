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
        Schema::create('tender_concept_block', function (Blueprint $table) {
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('concept_block_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('concept_block_version_id')->constrained('concept_block_versions')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['tender_id', 'concept_block_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_concept_block');
    }
};
