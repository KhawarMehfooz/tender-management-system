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
        Schema::create('service_category_cost_driver_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_category_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('type');
            $table->string('unit')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['service_category_id', 'field_key']);
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_category_cost_driver_fields');
    }
};
