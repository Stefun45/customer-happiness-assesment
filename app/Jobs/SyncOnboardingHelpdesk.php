<?php

namespace App\Jobs;

use App\Models\IntegrationSync;
use App\Services\OnboardingHelpdeskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOnboardingHelpdesk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function handle(OnboardingHelpdeskService $service): void
    {
        $sync = IntegrationSync::firstOrCreate(
            ['source' => 'onboarding_helpdesk'],
            ['status' => 'pending']
        );

        try {
            $sync->update(['status' => 'running']);

            $service->syncConversations();

            $sync->update([
                'status' => 'success',
                'last_synced_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncOnboardingHelpdesk failed', ['error' => $e->getMessage()]);
            $sync->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
