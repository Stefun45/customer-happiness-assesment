<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->input('filter', 'outstanding');

        $query = Invoice::with('client')->orderByDesc('due_at');

        if ($filter === 'outstanding') {
            $query->whereNull('paid_at');
        } elseif ($filter === 'overdue') {
            $query->whereNull('paid_at')->where('due_at', '<', now());
        }

        $invoices = $query->paginate(50);

        return Inertia::render('Invoices/Index', [
            'invoices' => [
                'data' => $invoices->map(fn(Invoice $i) => [
                    'id'             => $i->id,
                    'invoice_number' => $i->invoice_number,
                    'amount_pence'   => $i->amount_pence,
                    'currency'       => $i->currency,
                    'status'         => $i->status,
                    'issued_at'      => $i->issued_at?->toISOString(),
                    'due_at'         => $i->due_at?->toISOString(),
                    'paid_at'        => $i->paid_at?->toISOString(),
                    'is_overdue'     => $i->isOverdue(),
                    'client'         => $i->client ? [
                        'id'   => $i->client->id,
                        'name' => $i->client->name,
                    ] : null,
                ]),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page'    => $invoices->lastPage(),
                    'total'        => $invoices->total(),
                ],
            ],
            'filter' => $filter,
        ]);
    }
}
