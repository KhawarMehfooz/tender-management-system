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
        Schema::create('tender_certificate', function (Blueprint $table) {
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('certificate_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['tender_id', 'certificate_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_certificate');
    }
};
