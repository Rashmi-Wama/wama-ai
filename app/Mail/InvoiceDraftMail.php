<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceDraftMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice draft '.$this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody(),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as($this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }

    private function htmlBody(): string
    {
        $client = $this->invoice->client?->company_name ?? 'Client';
        $amount = number_format((float) $this->invoice->amount, 2);
        $due = optional($this->invoice->due_date)?->toDateString() ?? 'n/a';

        return <<<HTML
        <p>Hello {$client},</p>
        <p>Please find attached invoice draft <strong>{$this->invoice->invoice_number}</strong>.</p>
        <ul>
            <li>Amount: ₹{$amount}</li>
            <li>Due date: {$due}</li>
            <li>Status: {$this->invoice->payment_status}</li>
        </ul>
        <p>Regards,<br>Wama AI</p>
        HTML;
    }
}
