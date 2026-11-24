{{-- Server-rendered payslip PDF (dompdf), one payslip per page. Tables only: dompdf has no flexbox/grid. --}}
@php
    $amount = fn ($v) => number_format((float) $v, 2);
    $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $run->label }}</title>
    <style>
        @page { margin: 12mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #222; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .b { font-weight: bold; }
        .muted { color: #666; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }

        .head td { vertical-align: middle; }
        .company { font-size: 14pt; font-weight: bold; }
        .title { margin-top: 10px; padding: 6px 8px; background: #eef1f5; border: .75pt solid #555; font-size: 11pt; font-weight: bold; }
        .stamp { display: inline-block; border: 1.5pt solid #b7791f; color: #b7791f; padding: 1px 8px; font-weight: bold; letter-spacing: 1px; font-size: 8pt; }

        .info { border: .75pt solid #555; border-top: 0; }
        .info td { padding: 4px 8px; width: 25%; }
        .info td.k { color: #555; }

        .money { margin-top: 12px; border: .75pt solid #555; }
        .money th { background: #eef1f5; border-bottom: .75pt solid #555; padding: 5px 8px; text-align: left; }
        .money td { padding: 4px 8px; }
        .money .split { border-left: .75pt solid #555; }
        .money tr.total td { border-top: .75pt solid #555; font-weight: bold; }

        .net { margin-top: 12px; border: .75pt solid #555; }
        .net td { padding: 8px; vertical-align: middle; }
        .net .amount { font-size: 13pt; font-weight: bold; }

        .note { margin-top: 10px; font-size: 8pt; }
    </style>
</head>
<body>
@foreach ($payslips as $payslip)
    @php
        $earnings = $payslip->items->where('type', 'earning')->values();
        $deductions = $payslip->items->where('type', 'deduction')->values();
        $lines = max($earnings->count(), $deductions->count());
        $profile = $payslip->staff?->payrollProfile;
    @endphp
    <div class="page">
        <table class="head">
            <tr>
                <td style="width:20%">
                    @if (! empty($company['logo']))<img src="{{ $company['logo'] }}" alt="" style="max-height:50px;max-width:130px">@endif
                </td>
                <td>
                    <div class="company">{{ $company['name'] }}</div>
                    @if ($company['address'])<div class="muted">{!! nl2br(e($company['address'])) !!}</div>@endif
                </td>
                <td class="r" style="width:20%">
                    @if ($run->isDraft())<span class="stamp">DRAFT</span>@endif
                </td>
            </tr>
        </table>

        <div class="title">Payslip for {{ $run->label }}</div>
        <table class="info">
            <tr>
                <td class="k">Employee</td><td class="b">{{ $payslip->employee_name }}</td>
                <td class="k">Employee code</td><td>{{ $payslip->employee_code ?? '—' }}</td>
            </tr>
            <tr>
                <td class="k">Designation</td><td>{{ $payslip->designation ?? '—' }}</td>
                <td class="k">Department</td><td>{{ $payslip->department ?? '—' }}</td>
            </tr>
            <tr>
                <td class="k">PAN</td><td>{{ $payslip->pan ?? '—' }}</td>
                <td class="k">UAN</td><td>{{ $payslip->uan ?? '—' }}</td>
            </tr>
            <tr>
                <td class="k">Bank</td><td>{{ $profile?->bank_name ?? '—' }}</td>
                <td class="k">Account</td><td>{{ $profile?->masked_account_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="k">Paid days</td><td>{{ $days($payslip->paid_days) }} of {{ $payslip->period_days }}</td>
                <td class="k">Loss-of-pay days</td><td>{{ $days($payslip->lop_days) }}</td>
            </tr>
            <tr>
                <td class="k">Pay period</td><td>{{ $run->period_start->format('d/m/Y') }} – {{ $run->period_end->format('d/m/Y') }}</td>
                <td class="k">Tax regime</td><td>{{ \App\Models\StaffPayrollProfile::TAX_REGIMES[$payslip->tax_regime] ?? $payslip->tax_regime }}</td>
            </tr>
        </table>

        <table class="money">
            <thead>
            <tr>
                <th style="width:32%">Earnings</th><th class="r" style="width:18%">Amount (INR)</th>
                <th class="split" style="width:32%">Deductions</th><th class="r" style="width:18%">Amount (INR)</th>
            </tr>
            </thead>
            <tbody>
            @for ($i = 0; $i < $lines; $i++)
                <tr>
                    <td>{{ $earnings[$i]->name ?? '' }}</td>
                    <td class="r">{{ isset($earnings[$i]) ? $amount($earnings[$i]->amount) : '' }}</td>
                    <td class="split">{{ $deductions[$i]->name ?? '' }}</td>
                    <td class="r">{{ isset($deductions[$i]) ? $amount($deductions[$i]->amount) : '' }}</td>
                </tr>
            @endfor
            <tr class="total">
                <td>Gross earnings</td><td class="r">{{ $amount($payslip->gross_earnings) }}</td>
                <td class="split">Total deductions</td><td class="r">{{ $amount($payslip->total_deductions) }}</td>
            </tr>
            </tbody>
        </table>

        <table class="net">
            <tr>
                <td>
                    <div class="b">Net pay</div>
                    <div class="muted">{{ $inWords((float) $payslip->net_pay) }}</div>
                </td>
                <td class="r amount">INR {{ $amount($payslip->net_pay) }}</td>
            </tr>
        </table>

        <div class="note muted">
            Employer contributions (not deducted from pay): Provident Fund INR {{ $amount($payslip->employer_pf) }}
            (pension EPS INR {{ $amount($payslip->employer_eps) }}), ESI INR {{ $amount($payslip->employer_esi) }}.
        </div>
        <div class="note muted">This is a computer-generated payslip and does not need a signature.</div>
    </div>
@endforeach
</body>
</html>
