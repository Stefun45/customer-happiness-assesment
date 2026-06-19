<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\Client;
use App\Models\HappinessScore;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChurnRiskAlert extends Notification
{
    use Queueable;

    public function __construct(
        protected Client $client,
        protected HappinessScore $score,
        protected Alert $alert
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $concerns = implode("\n", array_map(
            fn($c) => "- {$c}",
            $this->score->key_concerns ?? []
        ));

        $actions = implode("\n", array_map(
            fn($a) => "- {$a}",
            $this->score->recommended_actions ?? []
        ));

        return (new MailMessage)
            ->subject("ALERT: High Churn Risk — {$this->client->name}")
            ->greeting("Churn Risk Alert")
            ->line("**{$this->client->name}** ({$this->client->company_name}) has been flagged as HIGH churn risk.")
            ->line("Happiness Score: **{$this->score->score}/10**")
            ->line("Summary: {$this->score->analysis_summary}")
            ->line("Key Concerns:\n{$concerns}")
            ->line("Recommended Actions:\n{$actions}")
            ->action('View Client', url("/clients/{$this->client->id}"))
            ->line('This alert was generated automatically by the Customer Happiness platform.');
    }
}
