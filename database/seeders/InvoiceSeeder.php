<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tax;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo clients with invoices in every status (draft, sent, overdue, paid,
 * cancelled), each with notes and payment terms. Safe to run again: it
 * skips clients that already have invoices.
 */
class InvoiceSeeder extends Seeder
{
    public function run(InvoiceService $invoices): void
    {
        $this->call([CompanySettingSeeder::class, TaxSeeder::class]);

        if (! DemoData::allowed()) {
            $this->command?->warn(DemoData::skippedMessage('demo clients and invoices'));

            return;
        }

        // Which taxes each client gets: same state (West Bengal) = CGST + SGST,
        // another state = IGST, export = none.
        $taxFor = [
            'ACME' => [['CGST', 9], ['SGST', 9]],
            'GLOBEX' => [['IGST', 18]],
            'BLUESKY' => [],
        ];
        $taxIds = fn (string $code) => collect($taxFor[$code])
            ->map(fn ($t) => Tax::where('name', $t[0])->where('rate', $t[1])->value('id'))
            ->filter()->values()->all();

        $terms = $invoices->setting(InvoiceService::SETTING_TERMS) ?: CompanySettingSeeder::DEFAULT_TERMS;
        $adminId = User::where('email', 'admin@example.com')->value('id');
        $today = CarbonImmutable::today();

        $clients = [
            'ACME' => ['name' => 'Acme Retail Pvt. Ltd.', 'contact_person' => 'Rahul Sen', 'email' => 'accounts@acme.example',
                'phone' => '+91 98300 00001', 'tax_id' => '19BBBBB1111B1Z5', 'address' => '12 Park Street', 'city' => 'Kolkata', 'country' => 'India'],
            'GLOBEX' => ['name' => 'Globex Logistics', 'contact_person' => 'Priya Nair', 'email' => 'finance@globex.example',
                'phone' => '+91 98200 00002', 'tax_id' => '27CCCCC2222C1Z5', 'address' => 'Andheri East', 'city' => 'Mumbai', 'country' => 'India'],
            'BLUESKY' => ['name' => 'BlueSky Software LLC', 'contact_person' => 'John Carter', 'email' => 'ap@bluesky.example',
                'address' => '500 Market St', 'city' => 'San Francisco', 'country' => 'USA'],
        ];
        foreach ($clients as $code => $data) {
            $clients[$code] = Client::firstOrCreate(['code' => $code], $data + ['status' => 'active']);
        }

        $samples = [
            [
                'client' => 'ACME', 'issued' => $today->subDays(45), 'status' => 'paid', 'paid_days_ago' => 20, 'reference' => 'UTR SBIN426000001',
                'notes' => "Thank you for your business.\nProject: e-commerce website, phase 1.",
                'items' => [
                    ['description' => 'Website design & development – phase 1', 'quantity' => 1, 'unit_price' => 120000],
                    ['description' => 'Domain registration (1 year)', 'quantity' => 1, 'unit_price' => 900],
                ],
            ],
            [
                'client' => 'ACME', 'issued' => $today->subDays(10), 'status' => 'sent',
                'notes' => 'Monthly maintenance for the e-commerce website.',
                'items' => [
                    ['description' => 'Website maintenance & support', 'quantity' => 1, 'unit_price' => 15000],
                    ['description' => 'Cloud hosting (months)', 'quantity' => 3, 'unit_price' => 2500],
                ],
            ],
            [
                'client' => 'GLOBEX', 'issued' => $today->subDays(40), 'status' => 'sent', // past due -> overdue
                'notes' => "Fleet tracking mobile app – milestone 2 (Android & iOS builds).\nThis invoice is overdue; please arrange payment.",
                'items' => [
                    ['description' => 'Mobile app development – milestone 2', 'quantity' => 1, 'unit_price' => 85000],
                    ['description' => 'QA & testing (hours)', 'quantity' => 24, 'unit_price' => 800],
                ],
            ],
            [
                'client' => 'GLOBEX', 'issued' => $today, 'status' => 'draft',
                'notes' => 'Draft – milestone 3 estimate, to be confirmed with Priya.',
                'items' => [
                    ['description' => 'Mobile app development – milestone 3', 'quantity' => 1, 'unit_price' => 95000],
                    ['description' => 'Play Store / App Store publishing', 'quantity' => 1, 'unit_price' => 5000],
                ],
            ],
            [
                'client' => 'BLUESKY', 'issued' => $today->subDays(25), 'status' => 'paid', 'paid_days_ago' => 5, 'reference' => 'SWIFT BSK-77812',
                'currency' => 'USD',
                'notes' => "Export of services under LUT – no GST charged.\nThank you for your business.",
                'items' => [
                    ['description' => 'Laravel API development (hours)', 'quantity' => 60, 'unit_price' => 30],
                    ['description' => 'Code review & deployment', 'quantity' => 1, 'unit_price' => 250],
                ],
            ],
            [
                'client' => 'BLUESKY', 'issued' => $today->subDays(30), 'status' => 'cancelled', 'currency' => 'USD',
                'notes' => 'Cancelled – raised in error, replaced by the next invoice.',
                'items' => [
                    ['description' => 'Laravel API development (hours)', 'quantity' => 55, 'unit_price' => 30],
                ],
            ],
        ];

        // Oldest first so the numbers run in date order.
        usort($samples, fn ($a, $b) => $a['issued'] <=> $b['issued']);

        foreach ($samples as $s) {
            $client = $clients[$s['client']];
            if (Invoice::where('client_id', $client->id)->where('notes', $s['notes'])->exists()) {
                continue;
            }

            $invoice = $invoices->save(new Invoice, [
                'client_id' => $client->id,
                'issue_date' => $s['issued']->toDateString(),
                'due_date' => $s['issued']->addDays(15)->toDateString(),
                'currency' => $s['currency'] ?? 'INR',
                'notes' => $s['notes'],
                'terms' => $terms,
                'items' => $s['items'],
                'tax_ids' => $taxIds($s['client']),
            ], $adminId);

            match ($s['status']) {
                'sent' => $invoices->changeStatus($invoice, 'sent'),
                'paid' => $invoices->changeStatus($invoices->changeStatus($invoice, 'sent'), 'paid', [
                    'paid_at' => $today->subDays($s['paid_days_ago'])->toDateString(),
                    'payment_reference' => $s['reference'],
                ]),
                'cancelled' => $invoices->changeStatus($invoice, 'cancelled'),
                default => null,
            };
        }
    }
}
