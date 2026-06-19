<?php

namespace App\Providers;

use App\Services\ClaudeAnalysisService;
use App\Services\FirefliesService;
use App\Services\FreeAgentService;
use App\Services\FreshdeskService;
use App\Services\OnboardingHelpdeskService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FreshdeskService::class, fn() => new FreshdeskService(
            apiKey: config('integrations.freshdesk.api_key'),
            domain: config('integrations.freshdesk.domain'),
        ));

        $this->app->singleton(FirefliesService::class, fn() => new FirefliesService(
            apiKey: config('integrations.fireflies.api_key'),
        ));

        $this->app->singleton(FreeAgentService::class, fn() => new FreeAgentService(
            accessToken: config('integrations.freeagent.access_token'),
        ));

        $this->app->singleton(OnboardingHelpdeskService::class, fn() => new OnboardingHelpdeskService(
            baseUrl: config('integrations.onboarding_helpdesk.base_url'),
            apiKey: config('integrations.onboarding_helpdesk.api_key'),
        ));

        $this->app->singleton(ClaudeAnalysisService::class, fn() => new ClaudeAnalysisService(
            apiKey: config('integrations.anthropic.api_key'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
