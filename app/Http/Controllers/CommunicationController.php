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
            'communications' => $communications,
        ]);
    }
}
