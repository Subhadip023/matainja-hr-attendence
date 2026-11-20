@extends('admin.layout')
@section('title', 'Invoices')

@section('content')
    @php
        $statusClass = ['draft' => '', 'sent' => 'info', 'paid' => 'ok', 'cancelled' => 'bad'];
        $money = fn ($v, $cur = 'INR') => ($cur === 'INR' ? '₹' : $cur.' ').number_format((float) $v, 2);
    @endphp
    <div class="page-head">
        <div>
            <h1>Invoices</h1>
            <p>Bills raised to clients, from draft to paid.</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="{{ route('admin.invoices.create') }}">+ New invoice</a>
        </div>
    </div>

    <div class="stats" style="margin-bottom:16px">
        <div class="stat"><div class="label">Outstanding (sent)</div><div class="value">{{ $money($outstanding) }}</div><div class="sub">{{ $counts['sent'] ?? 0 }} invoices awaiting payment</div></div>
        <div class="stat"><div class="label">Overdue</div><div class="value">{{ $overdue }}</div><div class="sub">Sent and past the due date</div></div>
        <div class="stat"><div class="label">Paid this month</div><div class="value">{{ $money($paidThisMonth) }}</div><div class="sub">{{ now()->format('F Y') }}</div></div>
    </div>

    <div class="pills" style="margin-bottom:12px">
        <a href="{{ route('admin.invoices.index', request()->except('status', 'page')) }}" class="{{ request('status') ? '' : 'active' }}">All {{ $counts->sum() }}</a>
        @foreach ($statuses as $st)
            <a href="{{ route('admin.invoices.index', array_merge(request()->except('page'), ['status' => $st])) }}" class="{{ request('status') === $st ? 'active' : '' }}">{{ ucfirst($st) }} {{ $counts[$st] ?? 0 }}</a>
        @endforeach
        <a href="{{ route('admin.invoices.index', array_merge(request()->except('page'), ['status' => 'overdue'])) }}" class="{{ request('status') === 'overdue' ? 'active' : '' }}">Overdue {{ $overdue }}</a>
    </div>

    <div class="card">
        <div class="card-head">
            <form method="GET" class="toolbar" style="flex:1">
                @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search invoice number or client">
                <select name="client" onchange="this.form.submit()">
                    <option value="">All clients</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
                <button class="btn" type="submit">Search</button>
                @if (request()->hasAny(['q', 'client']))<a class="btn ghost" href="{{ route('admin.invoices.index', request()->only('status')) }}">Clear</a>@endif
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Number</th><th>Client</th><th>Issued</th><th>Due</th><th class="right">Total</th><th>Status</th><th class="right">Actions</th></tr>
                </thead>
                <tbody>
                @forelse ($rows as $inv)
                    <tr>
                        <td class="mono"><a href="{{ route('admin.invoices.show', $inv) }}"><strong>{{ $inv->number }}</strong></a></td>
                        <td>{{ $inv->client?->name ?? '—' }}</td>
                        <td class="nowrap">{{ $inv->issue_date?->format('d M Y') }}</td>
                        <td class="nowrap">{{ $inv->due_date?->format('d M Y') ?? '—' }}</td>
                        <td class="right nowrap">{{ $money($inv->total, $inv->currency) }}</td>
                        <td>
                            <span class="badge {{ $statusClass[$inv->status] ?? '' }}">{{ ucfirst($inv->status) }}</span>
                            @if ($inv->is_overdue)<span class="badge warn">Overdue</span>@endif
                        </td>
                        <td class="right nowrap">
                            <a class="btn sm" href="{{ route('admin.invoices.show', $inv) }}">View</a>
                            <a class="btn sm" href="{{ route('admin.invoices.pdf', ['invoice' => $inv, 'inline' => 1]) }}" target="_blank" rel="noopener">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No invoices yet. <a href="{{ route('admin.invoices.create') }}">Create the first one</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links('admin.partials.pagination') }}
    </div>
@endsection
