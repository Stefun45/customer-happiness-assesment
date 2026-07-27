<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class SsoController extends Controller
{
    /**
     * Called server-to-server by TDC Reporting.
     * Validates the shared secret and returns a one-time signed login URL.
     */
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'email'  => 'required|email',
            'secret' => 'required|string',
        ]);

        if ($request->secret !== config('services.sso.secret')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $url = URL::temporarySignedRoute(
            'sso.login',
            now()->addSeconds(60),
            ['uid' => $user->id]
        );

        return response()->json(['url' => $url]);
    }

    /**
     * The user lands here via the signed URL and is logged in automatically.
     */
    public function login(Request $request): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect('/login')->with('error', 'This SSO link is invalid or has expired.');
        }

        $user = User::findOrFail($request->uid);
        Auth::login($user, true);

        return redirect('/dashboard');
    }
}
