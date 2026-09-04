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
            $table->boolean('is_archived')->default(false)->after('status');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->foreignUuid('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->text('invalidity_reason')->nullable()->after('archived_by');
            $table->timestamp('invalidated_at')->nullable()->after('invalidity_reason');
            $table->foreignUuid('invalidated_by')->nullable()->after('invalidated_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropConstrainedForeignId('invalidated_by');
            $table->dropColumn(['is_archived', 'archived_at', 'invalidity_reason', 'invalidated_at']);
        });
    }
};
