<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You\'ve been invited to Customer Happiness');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation', with: [
            'url'         => url("/invitations/{$this->invitation->token}/accept"),
            'inviterName' => $this->invitation->invitedBy->name ?? 'Your team',
            'expiresAt'   => $this->invitation->expires_at->format('d M Y'),
        ]);
    }
}
