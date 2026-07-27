<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'role'  => 'required|in:admin,viewer',
        ]);

        // Replace any existing pending invite for this address
        Invitation::where('email', $request->email)->whereNull('accepted_at')->delete();

        $invitation = Invitation::create([
            'email'      => $request->email,
            'token'      => Str::random(64),
            'role'       => $request->role,
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($request->email)->send(new InvitationMail($invitation));

        return back()->with('success', "Invitation sent to {$request->email}.");
    }

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return redirect('/login')->with('error', 'This invitation is invalid or has expired.');
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'email' => $invitation->email,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return redirect('/login')->with('error', 'This invitation is invalid or has expired.');
        }

        $request->validate([
            'name'                  => 'required|string|max:255',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $invitation->email,
            'password' => $request->password,
            'role'     => $invitation->role,
        ]);

        $invitation->update(['accepted_at' => now()]);

        Auth::login($user);

        return redirect('/dashboard');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();
        return back()->with('success', 'Invitation cancelled.');
    }
}
