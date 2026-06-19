<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        $invoices = Invoice::with('client')
            ->whereNull('paid_at')
            ->orderByDesc('due_at')
            ->paginate(50);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }
}
