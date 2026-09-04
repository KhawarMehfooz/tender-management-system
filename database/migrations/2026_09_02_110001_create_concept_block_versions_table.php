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
        Schema::create('concept_block_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('concept_block_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('content');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->unique(['concept_block_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concept_block_versions');
    }
};
