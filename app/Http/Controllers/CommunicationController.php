<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $source = $request->string('source')->trim()->toString();

        $communications = Communication::with('client')
            ->when($source, fn($q) => $q->where('source', $source))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%")
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
}
