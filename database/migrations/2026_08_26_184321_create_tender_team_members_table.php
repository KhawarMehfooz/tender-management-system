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
        Schema::create('tender_team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('functional_role');
            $table->timestamps();

            $table->unique(['tender_id', 'user_id', 'functional_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_team_members');
    }
};
