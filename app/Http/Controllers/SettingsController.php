<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if (!$request->user()->canManageAccounts()) {
            return redirect('/dashboard')->with('error', 'You do not have permission to access that page.');
        }

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
            ['name' => 'FreeAgent',     'key' => 'freeagent',  'configured' => !empty(config('integrations.freeagent_tools.token'))],
            ['name' => 'Anthropic (AI)','key' => 'anthropic',  'configured' => !empty(config('integrations.anthropic.api_key'))],
        ];

        return Inertia::render('Settings/Index', [
            'users'               => $users,
            'pending_invitations' => $pendingInvitations,
            'integrations'        => $integrations,
            'last_synced_at'      => Cache::get('last_synced_at'),
            'current_user_id'     => auth()->id(),
            'can_manage_users'    => $request->user()->isAdmin(),
            'roles'               => User::ROLES,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $request->validate(['role' => ['required', Rule::in(array_keys(User::ROLES))]]);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $user->update(['role' => $request->input('role')]);

        // Customers can only be managed by admins and account managers
        if (!$user->canManageAccounts()) {
            \App\Models\Client::where('account_manager_id', $user->id)->update(['account_manager_id' => null]);
        }

        return back()->with('success', "{$user->name} is now " . strtolower(User::ROLES[$user->role]) . '.');
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
