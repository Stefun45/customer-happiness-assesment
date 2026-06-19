<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class SyncController extends Controller
{
    public function trigger(): RedirectResponse
    {
        Artisan::call('app:sync-all');

        return back()->with('success', 'All sync jobs have been dispatched.');
    }
}
