<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(): Response
    {
        $months = collect(range(5, 0))->map(function (int $ago) {
            $start = now()->subMonths($ago)->startOfMonth();
            $end = now()->subMonths($ago)->endOfMonth();
            $label = $start->format('M Y');

            $billed = (float) Invoice::query()
                ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            $collected = (float) Payment::query()
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            return [
                'label' => $label,
                'billed' => round($billed, 2),
                'collected' => round($collected, 2),
            ];
        })->values();

        $statusBreakdown = Invoice::query()
            ->selectRaw('payment_status, COUNT(*) as count, SUM(amount - paid_amount) as outstanding')
            ->groupBy('payment_status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->payment_status,
                'count' => (int) $row->count,
                'outstanding' => round(max(0, (float) $row->outstanding), 2),
            ]);

        $topClients = Client::query()
            ->withSum('invoices as billed', 'amount')
            ->withSum('invoices as paid', 'paid_amount')
            ->get()
            ->map(fn (Client $client) => [
                'client' => $client->company_name,
                'billed' => round((float) $client->billed, 2),
                'outstanding' => round(max(0, (float) $client->billed - (float) $client->paid), 2),
            ])
            ->sortByDesc('outstanding')
            ->take(6)
            ->values();

        $outstandingTotal = (float) Invoice::query()
            ->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])
            ->selectRaw('SUM(amount - paid_amount) as total')
            ->value('total');

        $delayedProjects = Project::query()
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', now()->toDateString())
            ->whereNotIn('project_status', ['completed', 'cancelled'])
            ->count();

        return Inertia::render('Analytics/Index', [
            'summary' => [
                'active_clients' => Client::query()->where('status', 'active')->count(),
                'active_projects' => Project::query()->whereIn('project_status', ['pending', 'in_progress'])->count(),
                'outstanding_total' => round(max(0, $outstandingTotal), 2),
                'collected_this_month' => round((float) Payment::query()
                    ->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
                    ->sum('amount'), 2),
                'delayed_projects' => $delayedProjects,
            ],
            'revenueByMonth' => $months,
            'invoiceStatus' => $statusBreakdown,
            'topClients' => $topClients,
        ]);
    }
}
