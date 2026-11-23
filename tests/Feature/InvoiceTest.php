<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Tax;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Database\Seeders\CompanySettingSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InvoiceSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Tax $cgst;

    private Tax $sgst;

    private Tax $igst;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->client = Client::create(['name' => 'Acme Corp', 'code' => 'ACME', 'tax_id' => '19ABCDE1234F1Z5', 'city' => 'Kolkata']);
        $this->cgst = Tax::create(['name' => 'CGST', 'rate' => 9, 'is_default' => true]);
        $this->sgst = Tax::create(['name' => 'SGST', 'rate' => 9, 'is_default' => true]);
        $this->igst = Tax::create(['name' => 'IGST', 'rate' => 18]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Subtotal 53,800.00; CGST 9% + SGST 9% = 9,684.00; total 63,484.00. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'issue_date' => '2026-11-20',
            'due_date' => '2026-12-05',
            'items' => [
                ['description' => 'Website development', 'quantity' => 1, 'unit_price' => 50000],
                ['description' => 'Hosting (months)', 'quantity' => 3, 'unit_price' => 1000],
                ['description' => 'Domain', 'quantity' => 1, 'unit_price' => 800],
            ],
            'tax_ids' => [$this->cgst->id, $this->sgst->id],
        ], $overrides);
    }

    public function test_api_creates_invoice_with_chosen_taxes_and_sequential_number(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));

        $this->postJson('/api/v1/invoices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.number', 'INV/26-27/0001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', '53800.00')
            ->assertJsonPath('data.tax_total', '9684.00')
            ->assertJsonPath('data.total', '63484.00')
            ->assertJsonPath('data.client.code', 'ACME')
            ->assertJsonCount(3, 'data.items')
            ->assertJsonCount(2, 'data.taxes')
            ->assertJsonPath('data.taxes.0.name', 'CGST')
            ->assertJsonPath('data.taxes.0.amount', '4842.00');

        $this->postJson('/api/v1/invoices', $this->payload())->assertJsonPath('data.number', 'INV/26-27/0002');
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoices.created']);
    }

    public function test_default_taxes_are_used_when_none_are_sent_and_empty_list_means_no_tax(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $payload = $this->payload();
        unset($payload['tax_ids']);

        $this->postJson('/api/v1/invoices', $payload)->assertCreated()
            ->assertJsonCount(2, 'data.taxes')->assertJsonPath('data.total', '63484.00');

        $this->postJson('/api/v1/invoices', $this->payload(['tax_ids' => []]))->assertCreated()
            ->assertJsonCount(0, 'data.taxes')->assertJsonPath('data.total', '53800.00');
    }

    public function test_switching_taxes_and_items_recalculates_totals(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $id = $this->postJson('/api/v1/invoices', $this->payload())->json('data.id');

        // Client in another state: swap CGST + SGST for IGST.
        $this->patchJson("/api/v1/invoices/{$id}", ['tax_ids' => [$this->igst->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.taxes')
            ->assertJsonPath('data.taxes.0.name', 'IGST')
            ->assertJsonPath('data.total', '63484.00');

        $this->patchJson("/api/v1/invoices/{$id}", ['items' => [['description' => 'Support', 'quantity' => 2, 'unit_price' => 1000]]])
            ->assertOk()
            ->assertJsonPath('data.total', '2360.00')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_changing_a_tax_rate_does_not_change_existing_invoices(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $id = $this->postJson('/api/v1/invoices', $this->payload())->json('data.id');

        $this->patchJson("/api/v1/taxes/{$this->cgst->id}", ['rate' => 10])->assertOk();
        $this->patchJson("/api/v1/invoices/{$id}", ['notes' => 'edited'])->assertOk()
            ->assertJsonPath('data.taxes.0.rate', '9.00')
            ->assertJsonPath('data.total', '63484.00');

        // Deleting a tax keeps the invoice's copy.
        $this->deleteJson("/api/v1/taxes/{$this->sgst->id}")->assertOk();
        $this->assertSame('63484.00', Invoice::find($id)->total);
        $this->assertSame(2, Invoice::find($id)->taxes()->count());
    }

    public function test_turned_off_tax_cannot_be_added(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $this->igst->update(['is_active' => false]);

        $this->postJson('/api/v1/invoices', $this->payload(['tax_ids' => [$this->igst->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('tax_ids');
    }

    public function test_tax_api_crud_and_permissions(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $id = $this->postJson('/api/v1/taxes', ['name' => 'Cess', 'rate' => 1, 'description' => 'Flood cess'])
            ->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');
        $this->postJson('/api/v1/taxes', ['name' => 'Cess', 'rate' => 1])->assertUnprocessable(); // duplicate
        $this->patchJson("/api/v1/taxes/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/v1/taxes?active=1')->assertOk()->assertJsonCount(3, 'data');

        Sanctum::actingAs($this->userWithRole('Manager'));
        $this->getJson('/api/v1/taxes')->assertOk()->assertJsonCount(4, 'data');
        $this->postJson('/api/v1/taxes', ['name' => 'X', 'rate' => 1])->assertForbidden();
    }

    public function test_invoice_needs_items_and_a_valid_client(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));

        $this->postJson('/api/v1/invoices', $this->payload(['items' => [], 'client_id' => 999, 'tax_ids' => [999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items', 'client_id', 'tax_ids.0']);
    }

    public function test_status_workflow_and_locking(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $id = $this->postJson('/api/v1/invoices', $this->payload())->json('data.id');

        // Draft can't jump straight to paid.
        $this->postJson("/api/v1/invoices/{$id}/status", ['status' => 'paid'])->assertUnprocessable();

        $this->postJson("/api/v1/invoices/{$id}/status", ['status' => 'sent'])->assertOk()->assertJsonPath('data.status', 'sent');
        $this->deleteJson("/api/v1/invoices/{$id}")->assertUnprocessable();

        $this->postJson("/api/v1/invoices/{$id}/status", ['status' => 'paid', 'paid_at' => '2026-11-25', 'payment_reference' => 'UTR123'])
            ->assertOk()
            ->assertJsonPath('data.paid_at', '2026-11-25')
            ->assertJsonPath('data.payment_reference', 'UTR123');

        // Paid invoices are final.
        $this->patchJson("/api/v1/invoices/{$id}", ['notes' => 'changed'])->assertUnprocessable();
    }

    public function test_overdue_filter_and_flag(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $invoice = Invoice::create([
            'client_id' => $this->client->id, 'number' => 'OLD-1', 'issue_date' => now()->subDays(40),
            'due_date' => now()->subDays(10), 'status' => 'sent', 'total' => 100,
        ]);

        $this->getJson('/api/v1/invoices?status=overdue')->assertOk()
            ->assertJsonPath('data.data.0.number', 'OLD-1')
            ->assertJsonPath('data.data.0.is_overdue', true);
        $this->assertTrue($invoice->is_overdue);
    }

    public function test_api_returns_invoice_pdf(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $id = $this->postJson('/api/v1/invoices', $this->payload())->json('data.id');

        $response = $this->get("/api/v1/invoices/{$id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_managers_can_view_but_employees_cannot(): void
    {
        Sanctum::actingAs($this->userWithRole('Manager'));
        $this->getJson('/api/v1/invoices')->assertOk();
        $this->postJson('/api/v1/invoices', $this->payload())->assertForbidden();

        Sanctum::actingAs($this->userWithRole('Employee'));
        $this->getJson('/api/v1/invoices')->assertForbidden();
        $this->getJson('/api/v1/taxes')->assertForbidden();
    }

    public function test_client_with_invoices_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $this->postJson('/api/v1/invoices', $this->payload())->assertCreated();

        $this->deleteJson("/api/v1/clients/{$this->client->id}")->assertUnprocessable();
        $this->assertModelExists($this->client);
    }

    public function test_super_admin_creates_and_prints_an_invoice_in_the_panel(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        CompanySetting::create(['key' => 'company.name', 'value' => 'Matainja Technologies']);
        CompanySetting::create(['key' => 'invoice.prefix', 'value' => 'MT']);

        $this->get('/admin')->assertSee(route('admin.invoices.index'), false);
        $this->get('/admin/invoices')->assertOk()->assertSee('+ New invoice');

        // New invoice form: tax checkboxes, defaults ticked.
        $html = $this->get('/admin/invoices/create?client='.$this->client->id)->assertOk()
            ->assertSee('Acme Corp (ACME)')->assertSee('CGST @ 9%')->assertSee('IGST @ 18%')->getContent();
        $this->assertStringContainsString('<select id="tax_ids" name="tax_ids[]" multiple', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$this->cgst->id.'"[^>]*selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="'.$this->igst->id.'"[^>]*selected/', $html);

        $payload = $this->payload();
        $payload['items'][] = ['description' => '', 'quantity' => 1, 'unit_price' => '']; // blank row is ignored
        $this->post('/admin/invoices', $payload)->assertRedirect()->assertSessionHasNoErrors();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('MT/26-27/0001', $invoice->number);
        $this->assertCount(3, $invoice->items);
        $this->assertSame('63484.00', $invoice->total);

        $this->get("/admin/invoices/{$invoice->id}")->assertOk()
            ->assertSee('MT/26-27/0001')->assertSee('Matainja Technologies')
            ->assertSee('CGST @ 9%')->assertSee('SGST @ 9%')->assertSee('Mark as sent');

        // Unticking every tax in the form removes them.
        $this->put("/admin/invoices/{$invoice->id}", array_diff_key($payload, ['tax_ids' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('53800.00', $invoice->fresh()->total);
        $this->put("/admin/invoices/{$invoice->id}", $payload)->assertSessionHasNoErrors();

        // PDF is generated on the server.
        $response = $this->get("/admin/invoices/{$invoice->id}/pdf")->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename=MT-26-27-0001.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('inline', $this->get("/admin/invoices/{$invoice->id}/pdf?inline=1")->headers->get('content-disposition'));

        // The PDF template carries the right content (PDF bytes are compressed, so check the HTML).
        $html = view('invoices.pdf', ['invoice' => $invoice->fresh()->load(['client', 'items', 'taxes']), 'company' => app(InvoiceService::class)->company()])->render();
        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('19ABCDE1234F1Z5', $html);
        $this->assertStringContainsString('CGST @ 9%', $html);
        $this->assertStringContainsString('4,842.00', $html);
        $this->assertStringContainsString('(INR) 63,484.00', $html);
        $this->assertStringContainsString('SIXTY THREE THOUSAND FOUR HUNDRED AND EIGHTY FOUR RUPEES ONLY', $html);
        $this->assertStringContainsString('Stamp &amp; Signature', $html);
        $this->assertStringContainsString('Matainja Technologies', $html);

        $this->post("/admin/invoices/{$invoice->id}/status", ['status' => 'sent'])->assertRedirect();
        $this->post("/admin/invoices/{$invoice->id}/status", ['status' => 'paid', 'paid_at' => '2026-11-22'])->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->get("/admin/invoices/{$invoice->id}/edit")->assertRedirect(route('admin.invoices.show', $invoice));

        // The client can't be deleted from the Clients page either.
        $this->delete("/admin/organisation/clients/{$this->client->id}")->assertSessionHas('error');
        $this->assertModelExists($this->client);
    }

    public function test_taxes_page_in_the_panel(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $this->get('/admin/organisation/taxes')->assertOk()->assertSee('CGST')->assertSee('Tick on new invoices');
        $this->post('/admin/organisation/taxes', ['name' => 'IGST', 'rate' => '5', 'is_default' => '0', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('taxes', ['name' => 'IGST', 'rate' => 5, 'is_active' => true]);

        $this->post('/admin/organisation/taxes', ['name' => 'IGST', 'rate' => '18', 'is_active' => '1'])
            ->assertSessionHasErrors('rate'); // same name + rate already exists
    }

    public function test_draft_can_be_deleted_in_the_panel(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $this->post('/admin/invoices', $this->payload());
        $invoice = Invoice::firstOrFail();

        $this->get("/admin/invoices/{$invoice->id}/edit")->assertOk()->assertSee('Website development');
        $this->delete("/admin/invoices/{$invoice->id}")->assertRedirect(route('admin.invoices.index'));
        $this->assertModelMissing($invoice);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseCount('invoice_taxes', 0);
    }

    public function test_seeders_add_company_details_taxes_and_demo_invoices_once(): void
    {
        CompanySetting::create(['key' => 'company.name', 'value' => 'My Real Company']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(InvoiceSeeder::class); // second run adds nothing

        // Existing values are kept; empty ones are filled.
        $this->assertSame('My Real Company', CompanySetting::where('key', 'company.name')->value('value'));
        $this->assertStringContainsString('IFSC', CompanySetting::where('key', 'invoice.default_terms')->value('value'));
        $this->assertSame(10, Tax::count()); // 3 from setUp are reused, 7 added

        $this->assertSame(6, Invoice::count());
        $this->assertEqualsCanonicalizing(['draft', 'sent', 'paid', 'cancelled'], Invoice::distinct()->pluck('status')->all());
        $this->assertSame(1, Invoice::all()->filter->is_overdue->count());
        $this->assertSame(0, Invoice::whereNull('terms')->orWhereNull('notes')->count());
        $this->assertSame(2, Invoice::where('currency', 'USD')->count());
        $this->assertSame(0, Invoice::where('total', '<=', 0)->count());

        // Same-state client gets CGST + SGST, other-state IGST, export none.
        $taxNames = fn (string $code) => Invoice::whereHas('client', fn ($q) => $q->where('code', $code))->first()->taxes->pluck('name')->sort()->values()->all();
        $this->assertSame(['CGST', 'SGST'], $taxNames('ACME'));
        $this->assertSame(['IGST'], $taxNames('GLOBEX'));
        $this->assertSame([], $taxNames('BLUESKY'));
    }

    public function test_logo_upload_shows_on_invoice_and_pdf_and_can_be_replaced_or_removed(): void
    {
        Storage::fake('local');
        $this->actingAs($this->userWithRole('Super Admin'));

        $this->put('/admin/settings', ['company_logo' => UploadedFile::fake()->image('logo.png', 400, 120)])
            ->assertSessionHasNoErrors();
        $first = CompanySetting::where('key', 'company.logo')->value('value');
        Storage::disk('local')->assertExists($first);
        $this->get('/admin/settings')->assertSee('data:image/png;base64,', false)->assertSee('Remove the current logo');

        // Logo appears on the invoice page and the PDF still renders with it.
        $this->post('/admin/invoices', $this->payload());
        $invoice = Invoice::firstOrFail();
        $this->get("/admin/invoices/{$invoice->id}")->assertSee('data:image/png;base64,', false);
        $this->assertStringStartsWith('%PDF', $this->get("/admin/invoices/{$invoice->id}/pdf")->assertOk()->getContent());
        $html = view('invoices.pdf', ['invoice' => $invoice->load(['client', 'items', 'taxes']), 'company' => app(InvoiceService::class)->company()])->render();
        $this->assertStringContainsString('<img src="data:image/png;base64,', $html);

        // Saving other settings keeps the logo; uploading a new one replaces the old file.
        $this->put('/admin/settings', ['invoicing' => ['company_name' => 'Matainja']])->assertSessionHasNoErrors();
        $this->assertSame($first, CompanySetting::where('key', 'company.logo')->value('value'));
        $this->put('/admin/settings', ['company_logo' => UploadedFile::fake()->image('new.jpg', 300, 100)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);

        // Only PNG/JPG up to 1 MB.
        $this->put('/admin/settings', ['company_logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('company_logo');
        $this->put('/admin/settings', ['company_logo' => UploadedFile::fake()->image('big.png')->size(2048)])->assertSessionHasErrors('company_logo');

        $second = CompanySetting::where('key', 'company.logo')->value('value');
        $this->put('/admin/settings', ['remove_logo' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(CompanySetting::where('key', 'company.logo')->value('value'));
        Storage::disk('local')->assertMissing($second);
        $this->get("/admin/invoices/{$invoice->id}")->assertDontSee('data:image/png;base64,', false);
    }

    public function test_pdf_with_long_payment_terms_fits_on_one_page(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $payload = $this->payload();
        $payload['terms'] = CompanySettingSeeder::DEFAULT_TERMS;
        $payload['notes'] = 'Thank you for the project.';
        $this->post('/admin/invoices', $payload)->assertSessionHasNoErrors();

        $pdf = app(InvoiceService::class)->pdf(Invoice::firstOrFail());
        $pdf->output();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_discount_is_taken_off_before_tax_and_payment_mode_is_saved(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));
        $payload = $this->payload() + ['discount' => 3800, 'payment_mode' => 'NEFT'];

        // 53,800 - 3,800 = 50,000 taxable; CGST + SGST 9% each = 4,500 + 4,500.
        $id = $this->postJson('/api/v1/invoices', $payload)->assertCreated()
            ->assertJsonPath('data.subtotal', '53800.00')
            ->assertJsonPath('data.discount', '3800.00')
            ->assertJsonPath('data.taxes.0.amount', '4500.00')
            ->assertJsonPath('data.tax_total', '9000.00')
            ->assertJsonPath('data.total', '59000.00')
            ->assertJsonPath('data.payment_mode', 'NEFT')
            ->json('data.id');

        $html = view('invoices.pdf', ['invoice' => Invoice::findOrFail($id)->load(['client', 'items', 'taxes']), 'company' => app(InvoiceService::class)->company()])->render();
        $this->assertStringContainsString('Payment Mode: NEFT', $html);
        $this->assertStringContainsString('- 3,800.00', $html);
        $this->assertStringContainsString('FIFTY NINE THOUSAND RUPEES ONLY', $html);

        // Removing the discount brings the full subtotal back into tax; the PDF still shows a zero discount line.
        $this->putJson("/api/v1/invoices/{$id}", ['discount' => 0])->assertOk()->assertJsonPath('data.total', '63484.00');
        $html = view('invoices.pdf', ['invoice' => Invoice::findOrFail($id)->load(['client', 'items', 'taxes']), 'company' => app(InvoiceService::class)->company()])->render();
        $this->assertMatchesRegularExpression('/Discount<\/td>\s*<td class="r">0\.00<\/td>/', $html);

        // A discount larger than the subtotal is refused and nothing changes.
        $this->putJson("/api/v1/invoices/{$id}", ['discount' => 60000])->assertUnprocessable()->assertJsonValidationErrors('discount');
        $this->assertSame('63484.00', Invoice::findOrFail($id)->total);
    }

    public function test_commission_fee_is_added_to_the_total_after_tax(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));

        // 53,800 + 9,684 tax (fee is not taxed) + 500 fee = 63,984.
        $id = $this->postJson('/api/v1/invoices', $this->payload() + ['commission_fee' => 500])->assertCreated()
            ->assertJsonPath('data.tax_total', '9684.00')
            ->assertJsonPath('data.commission_fee', '500.00')
            ->assertJsonPath('data.total', '63984.00')
            ->json('data.id');

        $html = view('invoices.pdf', ['invoice' => Invoice::findOrFail($id)->load(['client', 'items', 'taxes']), 'company' => app(InvoiceService::class)->company()])->render();
        $this->assertMatchesRegularExpression('/Commission Fee<\/td>\s*<td class="r">500\.00<\/td>/', $html);

        $this->putJson("/api/v1/invoices/{$id}", ['commission_fee' => 0])->assertOk()->assertJsonPath('data.total', '63484.00');
    }

    public function test_invoice_numbers_follow_gst_rules_and_restart_each_financial_year(): void
    {
        Sanctum::actingAs($this->userWithRole('HR Admin'));

        // March 2027 is still FY 2026-27; April 2027 starts a new series.
        $this->postJson('/api/v1/invoices', ['issue_date' => '2027-03-31', 'due_date' => null] + $this->payload())->assertJsonPath('data.number', 'INV/26-27/0001');
        $this->postJson('/api/v1/invoices', $this->payload())->assertJsonPath('data.number', 'INV/26-27/0002');
        $this->postJson('/api/v1/invoices', ['issue_date' => '2027-04-01', 'due_date' => null] + $this->payload())->assertJsonPath('data.number', 'INV/27-28/0001');

        // Typed numbers: at most 16 characters, only letters, digits, "-" and "/".
        $this->postJson('/api/v1/invoices', ['number' => 'MT/2026-27/00001'] + $this->payload())->assertCreated();
        $this->postJson('/api/v1/invoices', ['number' => 'MT/2026-27/000001'] + $this->payload())->assertJsonValidationErrors('number');
        $this->postJson('/api/v1/invoices', ['number' => 'MT_2026#1'] + $this->payload())->assertJsonValidationErrors('number');

        $this->assertSame('26-27', InvoiceService::financialYear(CarbonImmutable::parse('2026-04-01')));
        $this->assertSame('25-26', InvoiceService::financialYear(CarbonImmutable::parse('2026-03-31')));
        $this->assertSame('99-00', InvoiceService::financialYear(CarbonImmutable::parse('2099-12-01')));
    }

    public function test_invoice_prefix_is_limited_so_numbers_fit_gst_length(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $this->put('/admin/settings', ['invoicing' => ['invoice_prefix' => 'MATAIN']])->assertSessionHasErrors('invoicing.invoice_prefix');
        $this->put('/admin/settings', ['invoicing' => ['invoice_prefix' => 'MT/X']])->assertSessionHasErrors('invoicing.invoice_prefix');
        $this->put('/admin/settings', ['invoicing' => ['invoice_prefix' => 'MATIN']])->assertSessionHasNoErrors();

        $number = app(InvoiceService::class)->nextNumber(CarbonImmutable::parse('2026-11-23'));
        $this->assertSame('MATIN/26-27/0001', $number);
        $this->assertLessThanOrEqual(16, strlen($number));
    }

    public function test_admin_form_saves_discount_and_payment_mode(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $this->get('/admin/invoices/create')->assertOk()->assertSee('id="payment_mode"', false)->assertSee('id="discount"', false);

        $this->post('/admin/invoices', $this->payload() + ['discount' => '3800', 'payment_mode' => 'UPI'])->assertSessionHasNoErrors();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('UPI', $invoice->payment_mode);
        $this->assertSame('59000.00', $invoice->total);
        $this->get("/admin/invoices/{$invoice->id}")->assertSee('Payment mode')->assertSee('UPI')->assertSee('Discount');
    }

    public function test_amount_in_words_uses_indian_numbering(): void
    {
        $service = app(InvoiceService::class);

        $this->assertSame('One Lakh Sixty Thousand Four Hundred and Eighty Rupees Only', $service->amountInWords(160480));
        $this->assertSame('Two Crore Five Lakh and One Rupees Only', $service->amountInWords(20500001));
        $this->assertSame('Ninety Nine Rupees and Fifty Paise Only', $service->amountInWords(99.5));
        $this->assertSame('Zero Rupees Only', $service->amountInWords(0));
        $this->assertSame('One Thousand USD Only', $service->amountInWords(1000, 'usd'));
    }

    public function test_settings_page_saves_invoicing_details(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $this->get('/admin/settings')->assertOk()->assertSee('Invoicing');

        $this->put('/admin/settings', [
            'geofence_enforced' => '1',
            'invoicing' => ['company_name' => 'Matainja', 'company_tax_id' => '19XYZ', 'invoice_prefix' => 'MAT', 'invoice_due_days' => '30'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Matainja', CompanySetting::where('key', 'company.name')->value('value'));
        $this->assertSame('30', CompanySetting::where('key', 'invoice.due_days')->value('value'));
        $this->get('/admin/invoices/create')->assertSee('MAT/');
    }
}
