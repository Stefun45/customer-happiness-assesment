<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Client;
use App\Models\HappinessScore;
use App\Notifications\ChurnRiskAlert;
use App\Services\ClaudeAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AnalyseClientHappiness implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    // Declared with a default (not constructor-promoted) so jobs queued before
    // this property existed still unserialise with it set
    public bool $force = false;

    /**
     * $force re-scores even while an account manager's assessment holds
     * (used by the manual "Run AI Analysis" button).
     */
    public function __construct(protected Client $client, bool $force = false)
    {
        $this->force = $force;
    }

    public function handle(ClaudeAnalysisService $service): void
    {
        if (!$this->force && $this->client->accountManagerAssessmentHolds()) {
            return;
        }

        try {
            $result = $service->analyseClient($this->client);

            // Never save a placeholder: it would replace a real score
            if ($result === null) {
                return;
            }

            $score = HappinessScore::create([
                'client_id' => $this->client->id,
                'source' => HappinessScore::SOURCE_AI,
                'score' => $result['score'],
                'churn_risk' => $result['churn_risk'],
                'analysis_summary' => $result['summary'],
                'key_concerns' => $result['concerns'],
                'recommended_actions' => $result['actions'],
                'scored_at' => now(),
            ]);

            // Dispatch churn alert if high risk
            if ($result['churn_risk'] === 'high') {
                $alert = Alert::create([
                    'client_id' => $this->client->id,
                    'alert_type' => 'churn_risk_high',
                    'message' => "Client {$this->client->name} ({$this->client->company_name}) has been flagged as high churn risk. Score: {$result['score']}/10.",
                    'threshold_value' => $result['score'],
                ]);

                $alertEmail = config('integrations.alerts.account_manager_email');
                if ($alertEmail) {
                    Notification::route('mail', $alertEmail)
                        ->notify(new ChurnRiskAlert($this->client, $score, $alert));
                    $alert->update(['sent_at' => now()]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('AnalyseClientHappiness failed', [
                'client_id' => $this->client->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
