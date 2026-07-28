<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the enum to include happiness_review
        DB::statement("ALTER TABLE communications MODIFY source ENUM('freshdesk','fireflies','onboarding_helpdesk','happiness_review') NOT NULL");

        Schema::table('communications', function (Blueprint $table) {
            $table->text('tone_summary')->nullable()->after('sentiment_score');
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropColumn('tone_summary');
        });

        DB::statement("ALTER TABLE communications MODIFY source ENUM('freshdesk','fireflies','onboarding_helpdesk') NOT NULL");
    }
};
