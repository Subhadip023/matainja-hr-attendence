<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Staff;
use App\Models\WfhRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Work from home added by an admin for an employee. It is stored as an
 * already-approved WFH request, so check-in, the app and payroll treat it
 * like one the employee asked for.
 */
class StaffWfhController extends AdminController
{
    /** Employees who can no longer work, so get no new WFH. */
    private const INACTIVE_STATUSES = ['resigned', 'terminated'];

    public function store(Request $request, Staff $staff): RedirectResponse
    {
        if (in_array($staff->employment_status, self::INACTIVE_STATUSES, true)) {
            return back()->with('error', "{$staff->full_name} is {$staff->employment_status}; work from home can't be added.");
        }

        $today = $this->today()->toDateString();
        $data = $request->validateWithBag('wfh', [
            'from_date' => ['required', 'date', 'after_or_equal:'.$today],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'from_date.after_or_equal' => 'Work from home can start today at the earliest. For a past day, correct the attendance instead.',
            'to_date.after_or_equal' => 'The end date must be on or after the start date.',
        ]);
        $from = Carbon::parse($data['from_date'])->toDateString();
        $to = Carbon::parse($data['to_date'])->toDateString();

        $overlap = $staff->wfhRequests()->where('status', 'approved')
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)
            ->orderBy('from_date')->first();
        if ($overlap) {
            return back()->withInput()->withErrors([
                'from_date' => "Already working from home {$this->period($overlap)}. Pick other dates or cancel that one first.",
            ], 'wfh');
        }

        $wfh = $staff->wfhRequests()->create([
            'from_date' => $from,
            'to_date' => $to,
            'reason' => $data['reason'] ?? null,
            'status' => 'approved',
            'approver_id' => auth()->id(),
            'approver_comment' => 'Added by admin',
            'decided_at' => now(),
        ]);
        AuditLog::record('wfh.added_by_admin', $wfh, null, $wfh->toArray());

        $message = "{$staff->full_name} can work from home {$this->period($wfh)}.";
        // Added from the attendance list: go back there, keeping its date and filters.
        if ($request->input('return_to') === 'attendance') {
            return back()->with('success', $message);
        }

        return redirect()->to(route('admin.staff.edit', $staff).'#wfh')->with('success', $message);
    }

    /**
     * Cancel work from home that hasn't ended. If it already started, it
     * ends yesterday instead, so the days already worked keep their record.
     */
    public function cancel(Staff $staff, WfhRequest $wfhRequest): RedirectResponse
    {
        $today = $this->today();
        if ($wfhRequest->status !== 'approved' || $wfhRequest->to_date->lt($today)) {
            return back()->with('error', 'Only current or upcoming approved work from home can be cancelled.');
        }

        $before = $wfhRequest->toArray();
        if ($wfhRequest->from_date->lt($today)) {
            $wfhRequest->update(['to_date' => $today->subDay()->toDateString()]);
            $message = "Work from home for {$staff->full_name} now ends {$wfhRequest->to_date->format('d M Y')}; they're back in the office from today.";
        } else {
            $wfhRequest->update(['status' => 'cancelled']);
            $message = "Work from home {$this->period($wfhRequest)} for {$staff->full_name} cancelled.";
        }
        AuditLog::record('wfh.cancelled_by_admin', $wfhRequest, $before, $wfhRequest->fresh()->toArray());

        return redirect()->to(route('admin.staff.edit', $staff).'#wfh')->with('success', $message);
    }

    /** "on 26 Nov 2026" or "from 26 Nov to 28 Nov 2026". */
    private function period(WfhRequest $wfh): string
    {
        return $wfh->from_date->isSameDay($wfh->to_date)
            ? 'on '.$wfh->from_date->format('d M Y')
            : 'from '.$wfh->from_date->format('d M').' to '.$wfh->to_date->format('d M Y');
    }
}
