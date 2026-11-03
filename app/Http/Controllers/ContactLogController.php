<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ContactLog;
use App\Models\HappinessScore;
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

            // Optional first-hand assessment: all three together or none
            'assessment_score'      => 'nullable|required_with:assessment_churn_risk,assessment_reason|numeric|min:0|max:10',
            'assessment_churn_risk' => 'nullable|required_with:assessment_score|in:low,medium,high',
            'assessment_reason'     => 'nullable|required_with:assessment_score|string|max:2000',
        ]);

        $log = $client->contactLogs()->create([
            'contacted_at'     => $data['contacted_at'],
            'communication_id' => $data['communication_id'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'user_id'          => $request->user()->id,
        ]);

        if (isset($data['assessment_score'])) {
            // Becomes the current score and holds until new activity arrives
            $client->happinessScores()->create([
                'source'              => HappinessScore::SOURCE_ACCOUNT_MANAGER,
                'scored_by_user_id'   => $request->user()->id,
                'contact_log_id'      => $log->id,
                'score'               => $data['assessment_score'],
                'churn_risk'          => $data['assessment_churn_risk'],
                'analysis_summary'    => $data['assessment_reason'],
                'key_concerns'        => [],
                'recommended_actions' => [],
                'scored_at'           => now(),
            ]);
        }

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
