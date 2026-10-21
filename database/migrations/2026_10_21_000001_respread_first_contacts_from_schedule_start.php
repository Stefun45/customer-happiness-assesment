<?php

use App\Services\ContactScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First contacts were spread from the deploy date; re-spread every
     * never-contacted client from the schedule start (3 Nov 2026).
     */
    public function up(): void
    {
        if (Schema::hasTable('happiness_scores') && Schema::hasTable('contact_logs')) {
            app(ContactScheduleService::class)->spreadFirstContacts(force: true);
        }
    }

    public function down(): void
    {
        //
    }
};
