<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $invoices = Invoice::query()
            ->with('client:id,company_name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('payment_status', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Invoices/Form', [
            'invoice' => null,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Invoice::create($this->validated($request));

        return redirect()->route('invoices.index')->with('success', 'Invoice created successfully.');
    }

    public function edit(Invoice $invoice): Response
    {
        return Inertia::render('Invoices/Form', [
            'invoice' => $invoice,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $invoice->update($this->validated($request, $invoice->id));

        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    public function pdf(Invoice $invoice): HttpResponse
    {
        $invoice->loadMissing('client');

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'client' => $invoice->client,
        ])->download($invoice->invoice_number.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $invoiceId = null): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'invoice_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('invoices', 'invoice_number')->ignore($invoiceId),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', 'in:unpaid,partial,paid,overdue'],
        ]);
    }
}
