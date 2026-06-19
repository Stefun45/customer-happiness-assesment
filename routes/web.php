<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
Route::post('/clients/{client}/analyse', [ClientController::class, 'analyse'])->name('clients.analyse');

Route::get('/communications', [CommunicationController::class, 'index'])->name('communications.index');
Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');

Route::post('/sync', [SyncController::class, 'trigger'])->name('sync.trigger');
