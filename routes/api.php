<?php

use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

// Server-to-server SSO: TDC Reporting calls this to get a one-time login URL
Route::post('/sso/token', [SsoController::class, 'token']);
