<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function index(): Response
    {
        $communications = Communication::with('client')
            ->orderByDesc('occurred_at')
            ->paginate(50);

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
        ]);
    }
}
