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
        Schema::create('nuts_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('label');
            $table->unsignedTinyInteger('level');
            $table->uuid('parent_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('nuts_codes', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('nuts_codes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nuts_codes');
    }
};
