<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with('client:id,name,code')->latest('issue_date')->latest('id');

        if ($status = $request->query('status')) {
            $status === 'overdue'
                ? $query->where('status', 'sent')->whereDate('due_date', '<', today())
                : $query->where('status', $status);
        }
        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        return ApiResponder::success($query->paginate(20), 'Invoices retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $invoice = $this->invoices->save(new Invoice, $request->validate($this->invoices->rules()), $request->user()->id);
        AuditLog::record('invoices.created', $invoice, null, $invoice->toArray());

        return ApiResponder::success($invoice, 'Invoice created', 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return ApiResponder::success($invoice->load(['client', 'items', 'taxes']), 'Invoice retrieved');
    }

    /** The invoice as a PDF file (application/pdf), for the app to save or share. */
    public function pdf(Request $request, Invoice $invoice): Response
    {
        $pdf = $this->invoices->pdf($invoice);
        $name = $this->invoices->pdfFilename($invoice);

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $before = $invoice->load(['items', 'taxes'])->toArray();
        $invoice = $this->invoices->save($invoice, $request->validate($this->invoices->rules($invoice)));
        AuditLog::record('invoices.updated', $invoice, $before, $invoice->toArray());

        return ApiResponder::success($invoice, 'Invoice updated');
    }

    public function status(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Invoice::STATUSES)],
            'paid_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);
        $before = $invoice->toArray();
        $this->invoices->changeStatus($invoice, $data['status'], $data);
        AuditLog::record('invoices.status_changed', $invoice, $before, $invoice->toArray());

        return ApiResponder::success($invoice->load(['client', 'items', 'taxes']), "Invoice marked {$invoice->status}");
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== 'draft') {
            return ApiResponder::error('Only draft invoices can be deleted. Cancel it instead.', 422);
        }
        $invoice->delete();
        AuditLog::record('invoices.deleted', null, $invoice->toArray());

        return ApiResponder::success(null, 'Invoice deleted');
    }
}
