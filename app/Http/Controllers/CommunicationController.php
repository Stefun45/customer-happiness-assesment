<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyseTranscriptTone;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Communication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $source = $request->string('source')->trim()->toString();

        $communications = Communication::with('client:id,name')
            ->select(['id', 'client_id', 'source', 'subject', 'body', 'occurred_at', 'sentiment_score'])
            ->when($source, fn($q) => $q->where('source', $source))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhereHas('client', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('occurred_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Communications/Index', [
            'communications' => [
                'data' => $communications->map(fn($c) => [
                    'id'          => $c->id,
                    'source'      => $c->source,
                    'subject'     => $c->subject,
                    'body'        => $c->body ? mb_substr($c->body, 0, 200) : '',
                    'occurred_at' => $c->occurred_at?->toISOString(),
                    'client'      => $c->client ? [
                        'id'   => $c->client->id,
                        'name' => $c->client->name,
                    ] : null,
                ]),
                'meta' => [
                    'current_page' => $communications->currentPage(),
                    'last_page'    => $communications->lastPage(),
                    'total'        => $communications->total(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'source' => $source,
            ],
        ]);
    }

    public function show(Communication $communication): Response
    {
        $communication->load('client');
        $payload = $communication->raw_payload ?? [];

        $clients = Client::whereNull('lost_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name]);

        return Inertia::render('Communications/Show', [
            'clients' => $clients,
            'communication' => [
                'id'              => $communication->id,
                'source'          => $communication->source,
                'subject'         => $communication->subject,
                'body'            => $communication->body,
                'occurred_at'     => $communication->occurred_at?->toISOString(),
                'sentiment_score' => $communication->sentiment_score,
                'tone_summary'    => $communication->tone_summary,
                'client'          => $communication->client ? [
                    'id'   => $communication->client->id,
                    'name' => $communication->client->name,
                ] : null,
                // Fireflies-specific structured data from raw_payload
                'attendees'       => array_map(fn($a) => [
                    'name'  => $a['displayName'] ?? '',
                    'email' => $a['email'] ?? '',
                ], $payload['meeting_attendees'] ?? []),
                'sentences'       => array_map(fn($s) => [
                    'speaker' => $s['speaker_name'] ?? '',
                    'text'    => $s['text'] ?? '',
                ], $payload['sentences'] ?? []),
                'duration'        => $payload['duration'] ?? null,
                'summary'         => $payload['summary']['overview'] ?? null,
                'action_items'    => $payload['summary']['action_items'] ?? null,
            ],
        ]);
    }

    public function linkClient(Request $request, Communication $communication): RedirectResponse
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);

        $clientId = $request->integer('client_id');
        $communication->update(['client_id' => $clientId]);

        // If the review has an email, cache it as a contact so future syncs auto-match
        $email = $communication->raw_payload['email_address'] ?? null;
        if ($email && $communication->source === 'happiness_review') {
            ClientContact::firstOrCreate(
                ['client_id' => $clientId, 'email' => strtolower(trim($email))],
                ['name' => null, 'phone' => null]
            );
        }

        return back()->with('success', 'Client linked successfully.');
    }

    public function analyse(Communication $communication): RedirectResponse
    {
        AnalyseTranscriptTone::dispatch($communication)->onQueue('default');

        return back()->with('success', 'Tone analysis has been queued.');
    }
}
