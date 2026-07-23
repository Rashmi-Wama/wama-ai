<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $payments = Payment::query()
            ->with(['invoice:id,invoice_number,client_id', 'invoice.client:id,company_name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_mode', 'like', "%{$search}%")
                        ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Payments/Form', [
            'payment' => null,
            'invoices' => Invoice::query()
                ->with('client:id,company_name')
                ->latest()
                ->get(['id', 'invoice_number', 'client_id', 'amount', 'paid_amount']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $payment = Payment::create($data);
        $this->syncInvoicePaidAmount($payment->invoice_id);

        return redirect()->route('payments.index')->with('success', 'Payment recorded successfully.');
    }

    public function edit(Payment $payment): Response
    {
        return Inertia::render('Payments/Form', [
            'payment' => $payment,
            'invoices' => Invoice::query()
                ->with('client:id,company_name')
                ->latest()
                ->get(['id', 'invoice_number', 'client_id', 'amount', 'paid_amount']),
        ]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $oldInvoiceId = $payment->invoice_id;
        $payment->update($this->validated($request));
        $this->syncInvoicePaidAmount($oldInvoiceId);

        if ($payment->invoice_id !== $oldInvoiceId) {
            $this->syncInvoicePaidAmount($payment->invoice_id);
        }

        return redirect()->route('payments.index')->with('success', 'Payment updated successfully.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $invoiceId = $payment->invoice_id;
        $payment->delete();
        $this->syncInvoicePaidAmount($invoiceId);

        return redirect()->route('payments.index')->with('success', 'Payment deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_mode' => ['required', 'in:cash,bank_transfer,upi,cheque,card,other'],
        ]);
    }

    private function syncInvoicePaidAmount(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            return;
        }

        $paid = (float) Payment::query()->where('invoice_id', $invoiceId)->sum('amount');
        $invoice->paid_amount = $paid;

        if ($paid <= 0) {
            $invoice->payment_status = 'unpaid';
        } elseif ($paid >= (float) $invoice->amount) {
            $invoice->payment_status = 'paid';
        } else {
            $invoice->payment_status = 'partial';
        }

        $invoice->save();
    }
}
