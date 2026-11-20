@extends('admin.layout')
@section('title', 'Approvals')

@section('content')
    @php
        $statusBadge = ['approved' => 'ok', 'rejected' => 'bad', 'pending' => 'warn', 'withdrawn' => '', 'cancelled' => ''];
        $d = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
    @endphp
    <div class="page-head">
        <div>
            <h1>Approvals</h1>
            <p>Leave, work-from-home and attendance correction requests from every team.</p>
        </div>
        <div class="pills">
            <a href="{{ route('admin.approvals.index', ['kind' => $kind]) }}" class="{{ $status === 'pending' ? 'active' : '' }}">Pending</a>
            <a href="{{ route('admin.approvals.index', ['kind' => $kind, 'status' => 'decided']) }}" class="{{ $status !== 'pending' ? 'active' : '' }}">History</a>
        </div>
    </div>

    <nav class="tabs">
        @foreach ($kinds as $key => $label)
            <a href="{{ route('admin.approvals.index', ['kind' => $key, 'status' => $status]) }}" class="{{ $kind === $key ? 'active' : '' }}">
                {{ $label }} @if ($pendingCounts[$key])<span class="badge warn">{{ $pendingCounts[$key] }}</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Employee</th>
                    @if ($kind === 'leave')<th>Leave</th><th>Dates</th><th>Days</th>@endif
                    @if ($kind === 'wfh')<th>Dates</th>@endif
                    @if ($kind === 'correction')<th>Work date</th><th>Requested change</th>@endif
                    <th>Reason</th><th>Submitted</th><th>Status</th>
                    @if ($status === 'pending')<th class="right">Decision</th>@endif
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    @php
                        $staffName = $item->staff?->full_name ?? 'Unknown';
                        $summary = match ($kind) {
                            'leave' => $staffName.' · '.($item->leaveType?->name ?? 'Leave').' · '.$d($item->from_date).' to '.$d($item->to_date),
                            'wfh' => $staffName.' · WFH '.$d($item->from_date).' to '.$d($item->to_date),
                            'correction' => $staffName.' · correction for '.$d($item->work_date),
                        };
                        $note = match ($kind) {
                            'leave' => $item->approvals->whereNotNull('decision')->last()?->comments,
                            'wfh' => $item->approver_comment,
                            'correction' => $item->review_comment,
                        };
                        $decider = match ($kind) {
                            'leave' => $item->approvals->whereNotNull('decision')->last()?->approver?->name,
                            'wfh' => $item->approver?->name,
                            'correction' => $item->reviewer?->name,
                        };
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $staffName }}</strong>
                            <span class="sub">{{ $item->staff?->employee_code }} · {{ $item->staff?->department?->name ?? '—' }}</span>
                        </td>
                        @if ($kind === 'leave')
                            <td>{{ $item->leaveType?->name }}</td>
                            <td class="nowrap">{{ $d($item->from_date) }}@if (! $item->from_date->equalTo($item->to_date)) → {{ $d($item->to_date) }}@endif</td>
                            <td>{{ rtrim(rtrim((string) $item->total_days, '0'), '.') }}{{ $item->partial_day ? ' (half)' : '' }}</td>
                        @endif
                        @if ($kind === 'wfh')
                            <td class="nowrap">{{ $d($item->from_date) }}@if (! $item->from_date->equalTo($item->to_date)) → {{ $d($item->to_date) }}@endif</td>
                        @endif
                        @if ($kind === 'correction')
                            <td class="nowrap">{{ $d($item->work_date) }}</td>
                            <td class="small">
                                @foreach (array_filter($item->requested_values ?? []) as $field => $value)
                                    <div><span class="muted">{{ str_replace('_', ' ', $field) }}:</span>
                                        {{ is_string($value) && strtotime($value) && str_contains($value, ':') ? \Illuminate\Support\Carbon::parse($value)->setTimezone($tz)->format('d M, h:i A') : (is_scalar($value) ? $value : json_encode($value)) }}</div>
                                @endforeach
                                @if ($item->attendance)
                                    <div class="muted">was: {{ $item->attendance->check_in_at?->setTimezone($tz)->format('h:i A') ?? '—' }} → {{ $item->attendance->check_out_at?->setTimezone($tz)->format('h:i A') ?? '—' }}</div>
                                @endif
                            </td>
                        @endif
                        <td style="max-width:280px">{{ $item->reason ?: '—' }}</td>
                        <td class="nowrap small">{{ $item->created_at?->setTimezone($tz)->format('d M, h:i A') }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$item->status] ?? '' }}">{{ ucfirst($item->status) }}</span>
                            @if ($decider)<span class="sub">by {{ $decider }}</span>@endif
                            @if ($note)<span class="sub">“{{ $note }}”</span>@endif
                        </td>
                        @if ($status === 'pending')
                            <td class="right nowrap">
                                <button class="btn sm success" type="button" data-decision="approve" data-summary="{{ $summary }}"
                                        data-action="{{ route('admin.approvals.decide', [$kind, $item->id, 'approve']) }}">Approve</button>
                                <button class="btn sm danger" type="button" data-decision="reject" data-summary="{{ $summary }}"
                                        data-action="{{ route('admin.approvals.decide', [$kind, $item->id, 'reject']) }}">Reject</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">{{ $status === 'pending' ? 'Nothing waiting. All caught up.' : 'No decided requests yet.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('admin.partials.pagination') }}
    </div>
@endsection
