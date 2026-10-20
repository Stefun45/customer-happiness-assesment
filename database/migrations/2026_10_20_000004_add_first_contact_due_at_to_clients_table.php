<?php

use App\Services\ContactScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // When a never-contacted client is first due; spread so they don't all fall due at once
            $table->date('first_contact_due_at')->nullable()->after('account_manager_id');
        });

        // happiness_scores and contact_logs exist on any real install; guard for partial test migrations
        if (Schema::hasTable('happiness_scores') && Schema::hasTable('contact_logs')) {
            app(ContactScheduleService::class)->spreadFirstContacts();
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('first_contact_due_at');
        });
    }
};
