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
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn(['submission_deadline', 'bidder_question_deadline', 'site_visit_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dateTime('submission_deadline')->nullable();
            $table->dateTime('bidder_question_deadline')->nullable();
            $table->dateTime('site_visit_date')->nullable();
        });
    }
};
