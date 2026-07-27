<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $users = User::orderBy('name')->get()->map(fn(User $u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'role'       => $u->role,
            'created_at' => $u->created_at->toISOString(),
        ]);

        $pendingInvitations = Invitation::with('invitedBy')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(Invitation $i) => [
                'id'         => $i->id,
                'email'      => $i->email,
                'role'       => $i->role,
                'invited_by' => $i->invitedBy->name ?? 'Unknown',
                'expires_at' => $i->expires_at->toISOString(),
            ]);

        $integrations = [
            ['name' => 'CMP',           'key' => 'cmp',        'configured' => !empty(config('integrations.cmp.api_key'))],
            ['name' => 'Freshdesk',     'key' => 'freshdesk',  'configured' => !empty(config('integrations.freshdesk.api_key'))],
            ['name' => 'Fireflies',     'key' => 'fireflies',  'configured' => !empty(config('integrations.fireflies.api_key'))],
            ['name' => 'FreeAgent',     'key' => 'freeagent',  'configured' => !empty(config('integrations.freeagent.client_id'))],
            ['name' => 'Anthropic (AI)','key' => 'anthropic',  'configured' => !empty(config('integrations.anthropic.api_key'))],
        ];

        return Inertia::render('Settings/Index', [
            'users'               => $users,
            'pending_invitations' => $pendingInvitations,
            'integrations'        => $integrations,
            'last_synced_at'      => Cache::get('last_synced_at'),
            'current_user_id'     => auth()->id(),
        ]);
    }

    public function removeUser(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot remove your own account.');
        }

        $user->delete();

        return back()->with('success', "{$user->name} has been removed.");
    }
}
