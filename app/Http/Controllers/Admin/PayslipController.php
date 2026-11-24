<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollService;
use App\Services\PayslipExportService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Review one payslip of a run: see how it was worked out, override the LOP
 * days and add one-off adjustments while the run is a draft.
 */
class PayslipController extends AdminController
{
    public function __construct(private PayrollService $payroll) {}

    public function show(PayrollRun $payrollRun, Payslip $payslip): View
    {
        return view('admin.payroll.payslip', [
            'run' => $payrollRun,
            'payslip' => $payslip->load('items'),
            'adjustments' => $payrollRun->adjustments()->where('staff_id', $payslip->staff_id)->orderBy('id')->get(),
        ]);
    }

    public function pdf(Request $request, PayrollRun $payrollRun, Payslip $payslip, PayslipExportService $exports): Response
    {
        $pdf = $exports->pdf($payrollRun, new EloquentCollection([$payslip]));
        $name = $exports->pdfFilename($payrollRun, $payslip);

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    public function updateLop(Request $request, PayrollRun $payrollRun, Payslip $payslip): RedirectResponse
    {
        if (! $payrollRun->isDraft()) {
            return back()->with('error', 'This payroll is locked. Reopen it to make changes.');
        }
        $data = $request->validate([
            'lop_days_override' => ['nullable', 'numeric', 'min:0', 'max:'.$payslip->period_days, 'multiple_of:0.5'],
        ], ['lop_days_override.multiple_of' => 'Use whole or half days.']);

        $before = $payslip->only(['lop_days_override', 'lop_days', 'net_pay']);
        $override = $request->filled('lop_days_override') ? $data['lop_days_override'] : null;
        $this->recalculate($payrollRun, $payslip, fn () => $payslip->update(['lop_days_override' => $override]));
        AuditLog::record('payslip.lop_overridden', $payrollRun, $before, ['staff_id' => $payslip->staff_id, 'lop_days_override' => $override]);

        return $this->backToPayslip($payrollRun, $payslip, $override === null ? 'LOP days reset to the attendance figure.' : 'LOP days updated and the payslip recalculated.');
    }

    public function storeAdjustment(Request $request, PayrollRun $payrollRun, Payslip $payslip): RedirectResponse
    {
        if (! $payrollRun->isDraft()) {
            return back()->with('error', 'This payroll is locked. Reopen it to make changes.');
        }
        $data = $request->validate([
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        $this->recalculate($payrollRun, $payslip, function () use ($payrollRun, $payslip, $data, $request) {
            $adjustment = $payrollRun->adjustments()->create($data + [
                'staff_id' => $payslip->staff_id,
                'is_taxable' => $data['type'] === 'earning' && $request->boolean('is_taxable'),
                'created_by' => auth()->id(),
            ]);
            AuditLog::record('payroll.adjustment_added', $payrollRun, null, $adjustment->toArray());
        });

        return $this->backToPayslip($payrollRun, $payslip, ucfirst($data['type'])." \"{$data['name']}\" added and the payslip recalculated.");
    }

    public function destroyAdjustment(PayrollRun $payrollRun, PayrollAdjustment $adjustment): RedirectResponse
    {
        if (! $payrollRun->isDraft()) {
            return back()->with('error', 'This payroll is locked. Reopen it to make changes.');
        }
        $payslip = $payrollRun->payslips()->where('staff_id', $adjustment->staff_id)->firstOrFail();

        $this->recalculate($payrollRun, $payslip, function () use ($payrollRun, $adjustment) {
            AuditLog::record('payroll.adjustment_removed', $payrollRun, $adjustment->toArray());
            $adjustment->delete();
        });

        return $this->backToPayslip($payrollRun, $payslip, 'Adjustment removed and the payslip recalculated.');
    }

    /** Apply a change, then redo just this employee's payslip. */
    private function recalculate(PayrollRun $run, Payslip $payslip, callable $change): void
    {
        DB::transaction(function () use ($run, $payslip, $change) {
            $change();
            $this->payroll->process($run, $this->today(), [$payslip->staff_id]);
        });
    }

    /** Back to the employee's payslip, which was recreated with a new id (or dropped from the run). */
    private function backToPayslip(PayrollRun $run, Payslip $old, string $message): RedirectResponse
    {
        $payslip = $run->payslips()->where('staff_id', $old->staff_id)->first();
        if (! $payslip) {
            return redirect()->route('admin.payroll.show', $run)->with('error', "{$old->employee_name} is no longer part of this payroll (see Not included).");
        }

        return redirect()->route('admin.payroll.payslips.show', [$run, $payslip])->with('success', $message);
    }
}
