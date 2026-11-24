<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Services\PayslipExportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly payroll runs: start, review, recalculate, lock, mark paid.
 */
class PayrollController extends AdminController
{
    public function __construct(private PayrollService $payroll) {}

    public function index(): View
    {
        $today = $this->today();
        $latest = PayrollRun::orderByDesc('year')->orderByDesc('month')->first();
        $suggested = $latest
            ? CarbonImmutable::create($latest->year, $latest->month, 1)->addMonth()
            : $today->startOfMonth()->subMonth();

        return view('admin.payroll.index', [
            'runs' => PayrollRun::orderByDesc('year')->orderByDesc('month')->paginate(24),
            'suggestedMonth' => $suggested->gt($today) ? $today->format('Y-m') : $suggested->format('Y-m'),
            'maxMonth' => $today->format('Y-m'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $thisMonth = $this->today()->format('Y-m');
        $data = $request->validate(['month' => ['bail', 'required', 'date_format:Y-m', function (string $attribute, mixed $value, \Closure $fail) use ($thisMonth) {
            if ($value > $thisMonth) {
                $fail('Payroll can only be run for this month or an earlier one.');
            }
        }]]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month']);

        $existing = PayrollRun::where('year', $month->year)->where('month', $month->month)->first();
        if ($existing) {
            return redirect()->route('admin.payroll.show', $existing)->with('error', "Payroll for {$existing->label} already exists.");
        }

        $run = $this->payroll->createRun($month->year, $month->month, $this->today());
        AuditLog::record('payroll.created', $run, null, $run->only(['year', 'month', 'employee_count', 'total_net']));

        return redirect()->route('admin.payroll.show', $run)->with('success', "Payroll for {$run->label} calculated for {$run->employee_count} employees. Review it, then lock it.");
    }

    public function show(Request $request, PayrollRun $payrollRun, PayslipExportService $exports): View
    {
        $payslips = $payrollRun->payslips()
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('employee_name', 'like', "%{$s}%")->orWhere('employee_code', 'like', "%{$s}%")))
            ->withCount(['items as adjustments_count' => fn ($q) => $q->where('source', 'adjustment')])
            ->orderBy('employee_name')->paginate(50)->withQueryString();

        return view('admin.payroll.show', [
            'run' => $payrollRun->load(['processor:id,name', 'locker:id,name']),
            'payslips' => $payslips,
            'ecr' => $exports->ecr($payrollRun),
            'statutory' => $payrollRun->payslips()->selectRaw('SUM(employee_pf) AS pf, SUM(employer_pf) AS employer_pf, SUM(employee_esi) AS esi, SUM(employer_esi) AS employer_esi, SUM(professional_tax) AS pt, SUM(tds) AS tds')->first(),
        ]);
    }

    public function recalculate(PayrollRun $payrollRun): RedirectResponse
    {
        return $this->attempt(function () use ($payrollRun) {
            $this->payroll->process($payrollRun, $this->today());
            AuditLog::record('payroll.recalculated', $payrollRun, null, $payrollRun->only(['employee_count', 'total_net']));

            return "Payroll for {$payrollRun->label} recalculated from the latest salaries, attendance and leave.";
        });
    }

    public function lock(PayrollRun $payrollRun): RedirectResponse
    {
        return $this->attempt(function () use ($payrollRun) {
            $this->payroll->lock($payrollRun);
            AuditLog::record('payroll.locked', $payrollRun);

            return "Payroll for {$payrollRun->label} locked. The figures can no longer change.";
        });
    }

    public function reopen(PayrollRun $payrollRun): RedirectResponse
    {
        return $this->attempt(function () use ($payrollRun) {
            $this->payroll->reopen($payrollRun);
            AuditLog::record('payroll.reopened', $payrollRun);

            return "Payroll for {$payrollRun->label} reopened as a draft.";
        });
    }

    public function markPaid(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(function () use ($payrollRun, $data) {
            $this->payroll->markPaid($payrollRun, $data['paid_on'], $data['payment_reference'] ?? null);
            AuditLog::record('payroll.paid', $payrollRun, null, $data);

            return "Payroll for {$payrollRun->label} marked as paid.";
        });
    }

    public function destroy(PayrollRun $payrollRun): RedirectResponse
    {
        if (! $payrollRun->isDraft()) {
            return back()->with('error', 'Only a draft payroll can be deleted. Reopen it first.');
        }
        $label = $payrollRun->label;
        $before = $payrollRun->only(['year', 'month', 'employee_count', 'total_net']);
        $payrollRun->delete();
        AuditLog::record('payroll.deleted', null, $before);

        return redirect()->route('admin.payroll.index')->with('success', "Draft payroll for {$label} deleted.");
    }

    /** Every payslip of the run in one PDF, one per page. */
    public function pdf(Request $request, PayrollRun $payrollRun, PayslipExportService $exports): Response|RedirectResponse
    {
        $payslips = $payrollRun->payslips()->orderBy('employee_name')->get();
        if ($payslips->isEmpty()) {
            return back()->with('error', 'This payroll has no payslips.');
        }
        $pdf = $exports->pdf($payrollRun, $payslips);
        $name = $exports->pdfFilename($payrollRun);

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    /**
     * Net pay with full account numbers for the salary transfer. Only for
     * locked figures, and every download is audit-logged.
     */
    public function bankTransfer(PayrollRun $payrollRun, PayslipExportService $exports): StreamedResponse|RedirectResponse
    {
        if ($payrollRun->isDraft()) {
            return back()->with('error', 'Lock the payroll before downloading the bank transfer file, so the amounts can\'t change afterwards.');
        }
        [$header, $rows, $missing] = $exports->bankTransfer($payrollRun);
        AuditLog::record('payroll.bank_file_exported', $payrollRun, null, ['rows' => count($rows), 'missing_bank_details' => $missing]);

        return $this->csv(sprintf('bank-transfer-%04d-%02d.csv', $payrollRun->year, $payrollRun->month), $header, $rows);
    }

    /** EPFO ECR 2.0 file to upload on the Unified Portal. Locked figures only. */
    public function ecr(PayrollRun $payrollRun, PayslipExportService $exports): Response|RedirectResponse
    {
        if ($payrollRun->isDraft()) {
            return back()->with('error', 'Lock the payroll before downloading the PF ECR file, so the figures match what you pay.');
        }
        $ecr = $exports->ecr($payrollRun);
        if ($ecr['lines'] === []) {
            return back()->with('error', 'No one in this payroll can be filed: nobody has PF deducted with a 12-digit UAN.');
        }
        AuditLog::record('payroll.ecr_exported', $payrollRun, null, $ecr['totals'] + ['left_out' => count($ecr['left_out'])]);

        return response(implode("\n", $ecr['lines'])."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename='.$exports->ecrFilename($payrollRun),
        ]);
    }

    /** All payslip figures, for the accountant and PF / ESI / TDS filing. */
    public function register(PayrollRun $payrollRun, PayslipExportService $exports): StreamedResponse
    {
        [$header, $rows] = $exports->register($payrollRun);

        return $this->csv(sprintf('payroll-register-%04d-%02d.csv', $payrollRun->year, $payrollRun->month), $header, $rows);
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function csv(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            PayslipExportService::writeCsvRow($out, $header);
            foreach ($rows as $row) {
                PayslipExportService::writeCsvRow($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Run a status change and turn a refused change into an error message. */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            return back()->with('success', $action());
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
