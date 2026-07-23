<?php

namespace App\Services\Ai;

use App\Mail\InvoiceDraftMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class BusinessTools
{
    /**
     * OpenAI-compatible tool schemas the model can choose from.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            $this->functionDef('getOutstandingInvoices', 'List outstanding/overdue invoices, optionally filtered by minimum outstanding amount.', [
                'min_amount' => ['type' => 'number', 'description' => 'Minimum outstanding amount in INR'],
                'overdue_only' => ['type' => 'boolean', 'description' => 'If true, only overdue invoices'],
            ]),
            $this->functionDef('getSalesBetweenDates', 'Calculate sales (invoice totals) between two dates using invoice_date. If the user omits a year, use the current year.', [
                'start_date' => ['type' => 'string', 'description' => 'Start date in YYYY-MM-DD. Use current year when year is not specified.'],
                'end_date' => ['type' => 'string', 'description' => 'End date in YYYY-MM-DD. Use current year when year is not specified.'],
            ], ['start_date', 'end_date']),
            $this->functionDef('generateInvoice', 'Generate and save an invoice draft for a client/project with a professional description.', [
                'client_or_project' => ['type' => 'string', 'description' => 'Client or project name keyword, e.g. MKU or Vistaar Finance'],
                'description' => ['type' => 'string', 'description' => 'Optional custom invoice description'],
                'amount' => ['type' => 'number', 'description' => 'Optional explicit invoice amount'],
            ], ['client_or_project']),
            $this->functionDef('getPendingPayments', 'Show invoices with unpaid or partial payments.', [
                'min_amount' => ['type' => 'number', 'description' => 'Minimum pending amount filter'],
            ]),
            $this->functionDef('getTopClients', 'Rank clients by outstanding amount or total billed.', [
                'limit' => ['type' => 'integer', 'description' => 'Number of clients to return'],
                'by' => ['type' => 'string', 'enum' => ['outstanding', 'billed'], 'description' => 'Ranking metric'],
            ]),
            $this->functionDef('getDelayedProjects', 'List projects past deadline that are not completed. Optionally only unpaid.', [
                'limit' => ['type' => 'integer', 'description' => 'Max projects to return'],
                'unpaid_only' => ['type' => 'boolean', 'description' => 'If true, only delayed projects with pending payment'],
            ]),
            $this->functionDef('getOverdueClients', 'List clients who have overdue unpaid invoices.', [
                'min_amount' => ['type' => 'number', 'description' => 'Minimum overdue outstanding filter'],
            ]),
            $this->functionDef('getMonthlyRevenueSummary', 'Generate monthly revenue summary with billed, collected, and outstanding totals.', [
                'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month', 'this_year'], 'description' => 'Reporting period'],
            ]),
            $this->functionDef('summarizeBusinessHealth', 'Summarize business performance using live DB metrics for a period.', [
                'period' => ['type' => 'string', 'enum' => ['this_month', 'last_month', 'this_year'], 'description' => 'Reporting period'],
            ]),
            $this->functionDef('readProjectNotes', 'Read external MCP-style project notes markdown and optionally focus on a keyword like payment risk.', [
                'keyword' => ['type' => 'string', 'description' => 'Optional focus keyword like Vistaar, MKU, payment risk'],
            ]),
            $this->functionDef('generateInvoicePdf', 'Generate a downloadable PDF for an existing invoice. Find it by invoice id, invoice number, or client name. If a client has multiple invoices, use the latest invoice.', [
                'invoice_id' => ['type' => 'integer', 'description' => 'Invoice id'],
                'invoice_number' => ['type' => 'string', 'description' => 'Invoice number e.g. INV-20260708-ABCD'],
                'client_name' => ['type' => 'string', 'description' => 'Client/company name, e.g. Apex Retail'],
            ]),
            $this->functionDef('emailInvoiceDraft', 'Email an invoice draft to the client (or a provided email address).', [
                'invoice_id' => ['type' => 'integer', 'description' => 'Invoice id'],
                'invoice_number' => ['type' => 'string', 'description' => 'Invoice number'],
                'to_email' => ['type' => 'string', 'description' => 'Optional override recipient email'],
            ]),
            $this->functionDef('whatsappSummary', 'Build a WhatsApp-ready payment/operations summary with a shareable wa.me link.', [
                'focus' => ['type' => 'string', 'enum' => ['pending', 'overdue', 'month'], 'description' => 'Summary focus'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @return list<string>
     */
    public function names(): array
    {
        return [
            'getOutstandingInvoices',
            'getSalesBetweenDates',
            'generateInvoice',
            'getPendingPayments',
            'getTopClients',
            'getDelayedProjects',
            'getOverdueClients',
            'getMonthlyRevenueSummary',
            'summarizeBusinessHealth',
            'readProjectNotes',
            'generateInvoicePdf',
            'emailInvoiceDraft',
            'whatsappSummary',
        ];
    }

    public function call(string $name, array $arguments = []): array
    {
        return match ($name) {
            'getOutstandingInvoices' => $this->getOutstandingInvoices($arguments),
            'getSalesBetweenDates' => $this->getSalesBetweenDates($arguments),
            'generateInvoice' => $this->generateInvoice($arguments),
            'getPendingPayments' => $this->getPendingPayments($arguments),
            'getTopClients' => $this->getTopClients($arguments),
            'getDelayedProjects' => $this->getDelayedProjects($arguments),
            'getOverdueClients' => $this->getOverdueClients($arguments),
            'getMonthlyRevenueSummary' => $this->getMonthlyRevenueSummary($arguments),
            'summarizeBusinessHealth' => $this->summarizeBusinessHealth($arguments),
            'readProjectNotes' => $this->readProjectNotes($arguments),
            'generateInvoicePdf' => $this->generateInvoicePdf($arguments),
            'emailInvoiceDraft' => $this->emailInvoiceDraft($arguments),
            'whatsappSummary' => $this->whatsappSummary($arguments),
            default => [
                'error' => true,
                'message' => "Unknown tool: {$name}",
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getOutstandingInvoices(array $args = []): array
    {
        $minAmount = (float) ($args['min_amount'] ?? 0);
        $overdueOnly = $this->toBool($args['overdue_only'] ?? false);

        $invoices = Invoice::query()
            ->with('client:id,company_name')
            ->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])
            ->get()
            ->map(function (Invoice $invoice) {
                $outstanding = max(0, (float) $invoice->amount - (float) $invoice->paid_amount);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client' => $invoice->client?->company_name,
                    'amount' => (float) $invoice->amount,
                    'paid_amount' => (float) $invoice->paid_amount,
                    'outstanding' => $outstanding,
                    'due_date' => optional($invoice->due_date)?->toDateString(),
                    'payment_status' => $invoice->payment_status,
                    'is_overdue' => $invoice->due_date && $invoice->due_date->isPast() && $outstanding > 0,
                ];
            })
            ->filter(fn (array $row) => $row['outstanding'] >= $minAmount)
            ->when($overdueOnly, fn ($rows) => $rows->filter(fn (array $row) => $row['is_overdue']))
            ->values();

        return [
            'count' => $invoices->count(),
            'total_outstanding' => round($invoices->sum('outstanding'), 2),
            'invoices' => $invoices->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getSalesBetweenDates(array $args = []): array
    {
        $start = Carbon::parse($args['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $end = Carbon::parse($args['end_date'] ?? now()->toDateString())->endOfDay();

        $invoices = Invoice::query()
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'invoice_number', 'amount', 'paid_amount', 'invoice_date', 'payment_status']);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'invoice_count' => $invoices->count(),
            'total_billed' => round((float) $invoices->sum('amount'), 2),
            'total_collected' => round((float) $invoices->sum('paid_amount'), 2),
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => optional($invoice->invoice_date)?->toDateString(),
                'amount' => (float) $invoice->amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'payment_status' => $invoice->payment_status,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function generateInvoice(array $args = []): array
    {
        $needle = trim((string) ($args['client_or_project'] ?? ''));

        if ($needle === '') {
            return ['error' => true, 'message' => 'client_or_project is required'];
        }

        $project = Project::query()
            ->with('client')
            ->where(function ($query) use ($needle) {
                $query->where('project_name', 'like', "%{$needle}%")
                    ->orWhereHas('client', fn ($q) => $q->where('company_name', 'like', "%{$needle}%"));
            })
            ->first();

        $client = $project?->client
            ?? Client::query()->where('company_name', 'like', "%{$needle}%")->first();

        if (! $client) {
            return [
                'error' => true,
                'message' => "No client/project matched \"{$needle}\".",
            ];
        }

        $pendingAmount = null;
        $projectName = $project?->project_name;

        if ($project) {
            $pendingAmount = max(0, (float) $project->total_amount - (float) $project->payment_received);
        }

        $explicitAmount = isset($args['amount']) ? (float) $args['amount'] : null;
        $amount = $explicitAmount ?? ($pendingAmount > 0 ? $pendingAmount : 50000.0);

        $description = trim((string) ($args['description'] ?? ''));
        if ($description === '') {
            $description = $this->buildInvoiceDescription($client->company_name, $projectName);
        }

        $template = $this->readInvoiceTemplate();
        $taxPercent = (float) ($template['tax_percent'] ?? 18);
        $dueDays = (int) ($template['payment_terms_days'] ?? 15);
        $invoiceNumber = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays($dueDays)->toDateString(),
            'amount' => round($amount, 2),
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
        ]);

        return [
            'created' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client' => $client->company_name,
                'project' => $projectName,
                'amount' => (float) $invoice->amount,
                'tax_percent' => $taxPercent,
                'due_date' => optional($invoice->due_date)?->toDateString(),
                'payment_status' => $invoice->payment_status,
                'description' => $description,
                'pending_from_project' => $pendingAmount,
            ],
            'workflow' => [
                'identified_target' => $needle,
                'fetched_project' => (bool) $project,
                'calculated_pending_amount' => $pendingAmount,
                'generated_description' => $description,
                'saved_invoice_id' => $invoice->id,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getPendingPayments(array $args = []): array
    {
        $minAmount = (float) ($args['min_amount'] ?? 0);

        $rows = Invoice::query()
            ->with('client:id,company_name')
            ->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])
            ->get()
            ->map(function (Invoice $invoice) {
                $pending = max(0, (float) $invoice->amount - (float) $invoice->paid_amount);

                return [
                    'invoice_number' => $invoice->invoice_number,
                    'client' => $invoice->client?->company_name,
                    'amount' => (float) $invoice->amount,
                    'paid_amount' => (float) $invoice->paid_amount,
                    'pending_amount' => $pending,
                    'due_date' => optional($invoice->due_date)?->toDateString(),
                    'payment_status' => $invoice->payment_status,
                ];
            })
            ->filter(fn (array $row) => $row['pending_amount'] >= $minAmount)
            ->sortByDesc('pending_amount')
            ->values();

        return [
            'count' => $rows->count(),
            'total_pending' => round($rows->sum('pending_amount'), 2),
            'payments' => $rows->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getTopClients(array $args = []): array
    {
        $limit = max(1, (int) ($args['limit'] ?? 5));
        $by = ($args['by'] ?? 'outstanding') === 'billed' ? 'billed' : 'outstanding';

        $clients = Client::query()->with('invoices')->get()->map(function (Client $client) {
            $billed = (float) $client->invoices->sum('amount');
            $paid = (float) $client->invoices->sum('paid_amount');

            return [
                'client' => $client->company_name,
                'billed' => $billed,
                'paid' => $paid,
                'outstanding' => max(0, $billed - $paid),
                'invoice_count' => $client->invoices->count(),
            ];
        });

        $sorted = $by === 'billed'
            ? $clients->sortByDesc('billed')
            : $clients->sortByDesc('outstanding');

        return [
            'ranked_by' => $by,
            'clients' => $sorted->take($limit)->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getDelayedProjects(array $args = []): array
    {
        $limit = max(1, (int) ($args['limit'] ?? 10));
        $unpaidOnly = $this->toBool($args['unpaid_only'] ?? false);

        $projects = Project::query()
            ->with('client:id,company_name')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', now()->toDateString())
            ->whereNotIn('project_status', ['completed', 'cancelled'])
            ->orderBy('deadline')
            ->get()
            ->map(fn (Project $project) => [
                'project_name' => $project->project_name,
                'client' => $project->client?->company_name,
                'deadline' => optional($project->deadline)?->toDateString(),
                'days_overdue' => $project->deadline?->diffInDays(now()),
                'project_status' => $project->project_status,
                'total_amount' => (float) $project->total_amount,
                'payment_received' => (float) $project->payment_received,
                'pending_amount' => max(0, (float) $project->total_amount - (float) $project->payment_received),
            ])
            ->when($unpaidOnly, fn ($rows) => $rows->filter(fn (array $row) => $row['pending_amount'] > 0))
            ->take($limit)
            ->values();

        return [
            'count' => $projects->count(),
            'unpaid_only' => $unpaidOnly,
            'projects' => $projects->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getOverdueClients(array $args = []): array
    {
        $minAmount = (float) ($args['min_amount'] ?? 0);
        $outstanding = $this->getOutstandingInvoices([
            'min_amount' => $minAmount,
            'overdue_only' => true,
        ]);

        $grouped = collect($outstanding['invoices'] ?? [])
            ->groupBy(fn (array $row) => $row['client'] ?? 'Unknown client')
            ->map(function ($rows, $client) {
                return [
                    'client' => $client,
                    'overdue_invoices' => $rows->count(),
                    'total_overdue' => round($rows->sum('outstanding'), 2),
                    'invoices' => $rows->pluck('invoice_number')->values()->all(),
                ];
            })
            ->sortByDesc('total_overdue')
            ->values();

        return [
            'count' => $grouped->count(),
            'total_overdue' => round($grouped->sum('total_overdue'), 2),
            'clients' => $grouped->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getMonthlyRevenueSummary(array $args = []): array
    {
        return $this->summarizeBusinessHealth([
            'period' => $args['period'] ?? 'this_month',
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function summarizeBusinessHealth(array $args = []): array
    {
        $period = $args['period'] ?? 'this_month';

        [$start, $end, $label] = match ($period) {
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(), 'last month'],
            'this_year' => [now()->startOfYear(), now()->endOfYear(), 'this year'],
            default => [now()->startOfMonth(), now()->endOfMonth(), 'this month'],
        };

        $sales = $this->getSalesBetweenDates([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
        $outstanding = $this->getOutstandingInvoices();
        $delayed = $this->getDelayedProjects(['limit' => 5]);
        $topClients = $this->getTopClients(['limit' => 3, 'by' => 'outstanding']);
        $paymentsThisPeriod = Payment::query()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        return [
            'period' => $label,
            'sales' => $sales,
            'payments_collected' => round((float) $paymentsThisPeriod, 2),
            'outstanding' => [
                'count' => $outstanding['count'],
                'total' => $outstanding['total_outstanding'],
            ],
            'delayed_projects' => $delayed,
            'top_outstanding_clients' => $topClients['clients'],
            'active_clients' => Client::query()->where('status', 'active')->count(),
            'active_projects' => Project::query()->whereIn('project_status', ['pending', 'in_progress'])->count(),
        ];
    }

    /**
     * MCP-style external source: local markdown project notes.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function readProjectNotes(array $args = []): array
    {
        $path = config('ai.mcp.project_notes_path');

        if (! File::exists($path)) {
            return [
                'error' => true,
                'message' => 'Project notes file not found.',
                'path' => $path,
            ];
        }

        $content = File::get($path);
        $keyword = trim((string) ($args['keyword'] ?? ''));

        if ($keyword !== '') {
            $sections = preg_split('/\n(?=## )/u', $content) ?: [$content];
            $matched = array_values(array_filter(
                $sections,
                fn (string $section) => Str::contains(Str::lower($section), Str::lower($keyword))
            ));

            return [
                'source' => 'mcp:project-notes.md',
                'keyword' => $keyword,
                'matched_sections' => count($matched),
                'content' => $matched === [] ? $content : implode("\n", $matched),
            ];
        }

        return [
            'source' => 'mcp:project-notes.md',
            'content' => $content,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function generateInvoicePdf(array $args = []): array
    {
        $invoice = $this->findInvoice($args);

        if (! $invoice) {
            return ['error' => true, 'message' => 'Invoice not found. Provide invoice_id or invoice_number.'];
        }

        $invoice->loadMissing('client');
        $directory = storage_path('app/invoices');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = $invoice->invoice_number.'.pdf';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'client' => $invoice->client,
        ])->save($path);

        return [
            'created' => true,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client' => $invoice->client?->company_name,
            'amount' => (float) $invoice->amount,
            'pdf_path' => $path,
            'download_url' => URL::route('invoices.pdf', $invoice, false),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function emailInvoiceDraft(array $args = []): array
    {
        $invoice = $this->findInvoice($args);

        if (! $invoice) {
            return ['error' => true, 'message' => 'Invoice not found. Provide invoice_id or invoice_number.'];
        }

        $invoice->loadMissing('client');
        $to = trim((string) ($args['to_email'] ?? $invoice->client?->email ?? ''));

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'error' => true,
                'message' => 'No valid recipient email. Set client email or pass to_email.',
            ];
        }

        $pdfResult = $this->generateInvoicePdf(['invoice_id' => $invoice->id]);
        if (($pdfResult['error'] ?? false) === true) {
            return $pdfResult;
        }

        Mail::to($to)->send(new InvoiceDraftMail($invoice, $pdfResult['pdf_path']));

        return [
            'sent' => true,
            'to' => $to,
            'invoice_number' => $invoice->invoice_number,
            'client' => $invoice->client?->company_name,
            'amount' => (float) $invoice->amount,
            'download_url' => $pdfResult['download_url'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function whatsappSummary(array $args = []): array
    {
        $focus = $args['focus'] ?? 'pending';

        if ($focus === 'overdue') {
            $data = $this->getOverdueClients();
            $lines = [
                'Wama AI — Overdue clients',
                sprintf('Clients: %d | Total overdue: ₹%s', $data['count'], number_format($data['total_overdue'], 2)),
            ];
            foreach (array_slice($data['clients'], 0, 5) as $row) {
                $lines[] = sprintf('• %s — ₹%s (%d inv)', $row['client'], number_format($row['total_overdue'], 2), $row['overdue_invoices']);
            }
        } elseif ($focus === 'month') {
            $data = $this->getMonthlyRevenueSummary(['period' => 'this_month']);
            $sales = $data['sales'] ?? [];
            $outstanding = $data['outstanding'] ?? [];
            $lines = [
                'Wama AI — Monthly revenue',
                sprintf('Billed: ₹%s (%d invoices)', number_format($sales['total_billed'] ?? 0, 2), $sales['invoice_count'] ?? 0),
                sprintf('Collected: ₹%s', number_format($data['payments_collected'] ?? 0, 2)),
                sprintf('Outstanding: ₹%s', number_format($outstanding['total'] ?? 0, 2)),
            ];
        } else {
            $data = $this->getPendingPayments();
            $lines = [
                'Wama AI — Pending payments',
                sprintf('Count: %d | Total pending: ₹%s', $data['count'], number_format($data['total_pending'], 2)),
            ];
            foreach (array_slice($data['payments'], 0, 5) as $row) {
                $lines[] = sprintf('• %s | %s | ₹%s', $row['invoice_number'], $row['client'] ?? '—', number_format($row['pending_amount'], 2));
            }
        }

        $text = implode("\n", $lines);
        $shareUrl = 'https://wa.me/?text='.rawurlencode($text);

        return [
            'focus' => $focus,
            'message' => $text,
            'whatsapp_share_url' => $shareUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function findInvoice(array $args): ?Invoice
    {
        if (! empty($args['invoice_id'])) {
            return Invoice::query()->find((int) $args['invoice_id']);
        }

        $number = trim((string) ($args['invoice_number'] ?? ''));
        if ($number !== '') {
            return Invoice::query()->where('invoice_number', $number)->first();
        }

        $clientName = trim((string) ($args['client_name'] ?? ''));
        if ($clientName !== '') {
            return Invoice::query()
                ->whereHas('client', function ($query) use ($clientName) {
                    $query->where('company_name', 'like', '%'.$clientName.'%');
                })
                ->latest('invoice_date')
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function buildInvoiceDescription(string $clientName, ?string $projectName): string
    {
        $haystack = Str::lower($clientName.' '.($projectName ?? ''));

        if (Str::contains($haystack, ['vistaar', 'accessibility', 'audit'])) {
            return 'Accessibility compliance implementation and audit fixes for RBI IS 17802 compliance.';
        }

        if (Str::contains($haystack, ['mku', 'modernization', 'platform'])) {
            return 'Platform modernization sprints, API hardening, and regression QA.';
        }

        if (Str::contains($haystack, ['retainer', 'support', 'apex'])) {
            return 'Monthly product support retainer and priority bug resolution.';
        }

        $label = $projectName ?: $clientName;

        return "Professional services delivery for {$label}, including implementation, validation, and delivery support.";
    }

    /**
     * @return array<string, mixed>
     */
    private function readInvoiceTemplate(): array
    {
        $path = config('ai.mcp.invoice_template_path');

        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?: [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function functionDef(string $name, string $description, array $properties = [], array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->groqSafeProperties($properties),
                    'required' => $required,
                ],
            ],
        ];
    }

    /**
     * Groq validates tool arguments strictly and often emits numbers/bools as strings.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, array<string, mixed>>
     */
    private function groqSafeProperties(array $properties): array
    {
        foreach ($properties as $key => $schema) {
            $type = $schema['type'] ?? null;

            if (in_array($type, ['number', 'integer', 'boolean'], true)) {
                $properties[$key]['type'] = 'string';
                $hint = match ($type) {
                    'boolean' => 'Use "true" or "false".',
                    'integer' => 'Provide as a numeric string if needed.',
                    default => 'Provide as a numeric string if needed.',
                };
                $properties[$key]['description'] = trim(($schema['description'] ?? $key).' '.$hint);
            }
        }

        return $properties;
    }

    private function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        $normalized = Str::lower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off', '' => false,
            default => $default,
        };
    }
}
