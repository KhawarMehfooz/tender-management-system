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
        Schema::create('competitor_price_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competitor_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('source');
            $table->date('observed_on')->nullable();
            $table->text('context')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitor_price_entries');
    }
};
