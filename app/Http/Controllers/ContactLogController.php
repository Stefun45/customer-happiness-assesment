<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ContactLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactLogController extends Controller
{
    /**
     * Fireflies calls for a client, for the "link a call" picker on the schedule page.
     */
    public function calls(Client $client): JsonResponse
    {
        return response()->json(self::firefliesCalls($client));
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'contacted_at'     => 'required|date|before_or_equal:today',
            'communication_id' => [
                'nullable',
                'integer',
                Rule::exists('communications', 'id')
                    ->where('client_id', $client->id)
                    ->where('source', 'fireflies'),
            ],
            'notes'            => 'nullable|string|max:10000',
        ]);

        $client->contactLogs()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', "Contact with {$client->name} marked as complete.");
    }

    public function destroy(Request $request, ContactLog $contactLog): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $contactLog->user_id === $request->user()->id, 403);

        $contactLog->delete();

        return back()->with('success', 'Contact removed.');
    }

    /**
     * The client's Fireflies calls, newest first, for linking to a completed contact.
     */
    public static function firefliesCalls(Client $client): array
    {
        return $client->communications()
            ->where('source', 'fireflies')
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get(['id', 'subject', 'occurred_at'])
            ->map(fn($c) => [
                'id'          => $c->id,
                'subject'     => $c->subject,
                'occurred_at' => $c->occurred_at?->toISOString(),
            ])
            ->all();
    }
}
