<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientNoteController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate(['body' => 'required|string|max:10000']);

        $client->notes()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Note added.');
    }

    public function destroy(Request $request, ClientNote $note): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $note->user_id === $request->user()->id, 403);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }
}
