<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tax;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends AdminController
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $rows = Invoice::with('client:id,name,code')
            ->when($status === 'overdue', fn ($q) => $q->where('status', 'sent')->whereDate('due_date', '<', $this->today()))
            ->when($status && $status !== 'overdue', fn ($q) => $q->where('status', $status))
            ->when($request->query('client'), fn ($q, $c) => $q->where('client_id', $c))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$s}%"))))
            ->latest('issue_date')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.invoices.index', [
            'rows' => $rows,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'statuses' => Invoice::STATUSES,
            'counts' => Invoice::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
            'overdue' => Invoice::where('status', 'sent')->whereDate('due_date', '<', $this->today())->count(),
            'outstanding' => (float) Invoice::where('status', 'sent')->sum('total'),
            'paidThisMonth' => (float) Invoice::where('status', 'paid')
                ->whereBetween('paid_at', [$this->today()->startOfMonth(), $this->today()->endOfMonth()])->sum('total'),
        ]);
    }

    public function create(Request $request): View
    {
        $today = $this->today();
        $invoice = new Invoice([
            'client_id' => $request->integer('client') ?: null,
            'issue_date' => $today,
            'due_date' => $this->invoices->defaultDueDate($today),
            'currency' => 'INR',
            'terms' => $this->invoices->setting(InvoiceService::SETTING_TERMS),
        ]);

        return view('admin.invoices.form', $this->formData($invoice) + [
            'nextNumber' => $this->invoices->nextNumber($today),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $invoice = $this->invoices->save(new Invoice, $this->validated($request), $request->user()->id);
        AuditLog::record('invoices.created', $invoice, null, $invoice->toArray());

        return redirect()->route('admin.invoices.show', $invoice)->with('success', "Invoice {$invoice->number} created as a draft.");
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['client', 'items', 'taxes', 'creator:id,name']);

        return view('admin.invoices.show', [
            'invoice' => $invoice,
            'company' => $this->invoices->company(),
            'transitions' => InvoiceService::TRANSITIONS[$invoice->status] ?? [],
        ]);
    }

    /** PDF generated on the server: downloads, or opens in the browser with ?inline=1. */
    public function pdf(Request $request, Invoice $invoice): Response
    {
        $pdf = $this->invoices->pdf($invoice);
        $name = $this->invoices->pdfFilename($invoice);

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->isLocked()) {
            return redirect()->route('admin.invoices.show', $invoice)->with('error', "A {$invoice->status} invoice can't be edited.");
        }

        return view('admin.invoices.form', $this->formData($invoice->load(['items', 'taxes'])));
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $before = $invoice->load(['items', 'taxes'])->toArray();
        $invoice = $this->invoices->save($invoice, $this->validated($request, $invoice));
        AuditLog::record('invoices.updated', $invoice, $before, $invoice->toArray());

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Invoice saved.');
    }

    public function status(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Invoice::STATUSES)],
            'paid_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);
        $before = $invoice->toArray();
        $this->invoices->changeStatus($invoice, $data['status'], $data);
        AuditLog::record('invoices.status_changed', $invoice, $before, $invoice->toArray());

        return back()->with('success', "Invoice {$invoice->number} marked {$invoice->status}.");
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be deleted. Cancel it instead.');
        }
        $invoice->delete();
        AuditLog::record('invoices.deleted', null, $invoice->toArray());

        return redirect()->route('admin.invoices.index')->with('success', "Invoice {$invoice->number} deleted.");
    }

    /** Drop the blank rows the form always shows before validating. */
    private function validated(Request $request, ?Invoice $invoice = null): array
    {
        $items = collect($request->input('items', []))
            ->filter(fn ($i) => filled($i['description'] ?? null) || filled($i['unit_price'] ?? null))
            ->values()->all();
        // No tax ticked = no tax (the checkboxes send nothing at all in that case).
        $request->merge(['items' => $items, 'tax_ids' => (array) $request->input('tax_ids', [])]);

        $rules = $this->invoices->rules($invoice);
        $rules['items'] = ['required', 'array', 'min:1', 'max:200'];

        return $request->validate($rules, ['items.required' => 'Add at least one line item.']);
    }

    private function formData(Invoice $invoice): array
    {
        $clients = Client::where('status', 'active')
            ->when($invoice->client_id, fn ($q) => $q->orWhere('id', $invoice->client_id))
            ->orderBy('name')->get(['id', 'name', 'code']);

        // Taxes on offer: active ones, plus any turned-off tax this invoice already has.
        $attached = $invoice->exists ? $invoice->taxes->pluck('tax_id')->filter()->all() : [];
        $taxes = Tax::where('is_active', true)->orWhereIn('id', $attached)->orderBy('name')->orderBy('rate')->get();
        $selected = $invoice->exists
            ? $attached
            : $taxes->where('is_default', true)->where('is_active', true)->pluck('id')->all();

        return [
            'invoice' => $invoice,
            'clients' => $clients,
            'taxes' => $taxes,
            'selectedTaxIds' => array_map('intval', (array) old('tax_ids', $selected)),
        ];
    }
}
