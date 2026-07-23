<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Database\Seeder;

class BusinessDemoSeeder extends Seeder
{
    public function run(): void
    {
        $vistaar = Client::updateOrCreate(
            ['email' => 'accounts@vistaarfinance.example'],
            [
                'company_name' => 'Vistaar Finance',
                'contact_person' => 'Ananya Mehta',
                'mobile' => '9876500001',
                'status' => 'active',
            ]
        );

        $mku = Client::updateOrCreate(
            ['email' => 'ops@mku.example'],
            [
                'company_name' => 'MKU Industries',
                'contact_person' => 'Rohan Kapoor',
                'mobile' => '9876500002',
                'status' => 'active',
            ]
        );

        $apex = Client::updateOrCreate(
            ['email' => 'finance@apexretail.example'],
            [
                'company_name' => 'Apex Retail',
                'contact_person' => 'Neha Shah',
                'mobile' => '9876500003',
                'status' => 'active',
            ]
        );

        $vistaarProject = Project::updateOrCreate(
            ['project_name' => 'Vistaar Accessibility Audit'],
            [
                'client_id' => $vistaar->id,
                'start_date' => now()->subMonths(2)->toDateString(),
                'deadline' => now()->subDays(10)->toDateString(),
                'total_amount' => 195000,
                'payment_received' => 120000,
                'project_status' => 'in_progress',
            ]
        );

        Project::updateOrCreate(
            ['project_name' => 'MKU Platform Modernization'],
            [
                'client_id' => $mku->id,
                'start_date' => now()->subMonths(3)->toDateString(),
                'deadline' => now()->subDays(5)->toDateString(),
                'total_amount' => 450000,
                'payment_received' => 200000,
                'project_status' => 'in_progress',
            ]
        );

        Project::updateOrCreate(
            ['project_name' => 'Apex Support Retainer'],
            [
                'client_id' => $apex->id,
                'start_date' => now()->startOfYear()->toDateString(),
                'deadline' => now()->endOfYear()->toDateString(),
                'total_amount' => 480000,
                'payment_received' => 160000,
                'project_status' => 'in_progress',
            ]
        );

        $inv1 = Invoice::updateOrCreate(
            ['invoice_number' => 'INV-DEMO-1001'],
            [
                'client_id' => $vistaar->id,
                'invoice_date' => now()->subDays(25)->toDateString(),
                'due_date' => now()->subDays(10)->toDateString(),
                'amount' => 120000,
                'paid_amount' => 45000,
                'payment_status' => 'overdue',
            ]
        );

        Invoice::updateOrCreate(
            ['invoice_number' => 'INV-DEMO-1002'],
            [
                'client_id' => $mku->id,
                'invoice_date' => now()->subDays(12)->toDateString(),
                'due_date' => now()->addDays(3)->toDateString(),
                'amount' => 250000,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
            ]
        );

        $inv3 = Invoice::updateOrCreate(
            ['invoice_number' => 'INV-DEMO-1003'],
            [
                'client_id' => $apex->id,
                'invoice_date' => now()->subDays(8)->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'amount' => 40000,
                'paid_amount' => 40000,
                'payment_status' => 'paid',
            ]
        );

        Payment::query()->whereIn('invoice_id', [$inv1->id, $inv3->id])->delete();

        Payment::create([
            'invoice_id' => $inv1->id,
            'amount' => 45000,
            'payment_date' => now()->subDays(20)->toDateString(),
            'payment_mode' => 'bank_transfer',
        ]);

        Payment::create([
            'invoice_id' => $inv3->id,
            'amount' => 40000,
            'payment_date' => now()->subDays(5)->toDateString(),
            'payment_mode' => 'upi',
        ]);

        // Keep reference quiet for static analysis.
        unset($vistaarProject);
    }
}
