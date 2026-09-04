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
            $table->timestamp('reminder_12_months_sent_at')->nullable()->after('contract_end_date');
            $table->timestamp('reminder_9_months_sent_at')->nullable()->after('reminder_12_months_sent_at');
            $table->timestamp('reminder_6_months_sent_at')->nullable()->after('reminder_9_months_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_12_months_sent_at',
                'reminder_9_months_sent_at',
                'reminder_6_months_sent_at',
            ]);
        });
    }
};
