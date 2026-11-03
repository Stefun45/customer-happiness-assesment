<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happiness_scores', function (Blueprint $table) {
            // ai, or account_manager for a first-hand assessment recorded with a contact
            $table->string('source')->default('ai')->after('client_id');
            $table->foreignId('scored_by_user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->foreignId('contact_log_id')->nullable()->after('scored_by_user_id')->constrained('contact_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('happiness_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_log_id');
            $table->dropConstrainedForeignId('scored_by_user_id');
            $table->dropColumn('source');
        });
    }
};
