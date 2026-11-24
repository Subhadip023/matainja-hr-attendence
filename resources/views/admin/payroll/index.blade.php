@extends('admin.layout')
@section('title', 'Payroll')

@section('content')
    @php $money = fn ($amount) => '₹'.number_format((float) $amount, 2); @endphp

    <div class="page-head">
        <div>
            <h1>Payroll</h1>
            <p>Monthly salary runs. Each run works out every employee's pay from their salary, attendance and leave, with PF, ESI, Professional Tax and TDS.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('admin.org.index', 'salary-components') }}">Salary components</a>
            <a class="btn" href="{{ route('admin.settings.index') }}#payroll">Payroll settings</a>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <form method="POST" action="{{ route('admin.payroll.store') }}" class="card-body toolbar">
            @csrf
            <div class="field" style="margin:0">
                <label for="month">Run payroll for</label>
                <input id="month" type="month" name="month" value="{{ old('month', $suggestedMonth) }}" max="{{ $maxMonth }}" required>
            </div>
            <button class="btn primary" type="submit" style="align-self:flex-end">Calculate payroll</button>
            <p class="hint" style="margin:0;align-self:flex-end">Creates a draft you can review and adjust before locking it.</p>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2>Payroll runs <span class="muted small">({{ $runs->total() }})</span></h2></div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Month</th><th>Status</th><th class="right">Employees</th><th class="right">Gross</th>
                    <th class="right">Deductions</th><th class="right">Net pay</th><th>Paid on</th><th class="right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td><strong>{{ $run->label }}</strong></td>
                        <td>@include('admin.payroll._status', ['status' => $run->status])</td>
                        <td class="right">{{ $run->employee_count }}</td>
                        <td class="right">{{ $money($run->total_gross) }}</td>
                        <td class="right">{{ $money($run->total_deductions) }}</td>
                        <td class="right"><strong>{{ $money($run->total_net) }}</strong></td>
                        <td>{{ $run->paid_on?->format('d M Y') ?? '—' }}</td>
                        <td class="right"><a class="btn sm" href="{{ route('admin.payroll.show', $run) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No payroll yet. Set each employee's salary (Employees → Salary), then calculate the first month above.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $runs->links('admin.partials.pagination') }}
    </div>
@endsection
