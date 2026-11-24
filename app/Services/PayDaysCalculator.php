<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out, day by day, which days of a pay period are paid and which are
 * loss of pay (LOP). Paid: attended (present / late / WFH), paid leave,
 * holidays, weekly offs and days still to come. LOP: absent with no approved
 * leave, unpaid leave, and days before the employee joined.
 */
class PayDaysCalculator
{
    /** Attendance statuses that count as a worked day. */
    private const WORKED = ['present', 'late', 'wfh'];

    /**
     * @param  Collection<int, Attendance>  $attendance  the employee's attendance in the period
     * @param  Collection<int, LeaveRequest>  $leaves  their approved leave overlapping the period (with leaveType)
     * @param  list<string>  $holidayDates  Y-m-d holidays that apply to the employee
     * @param  list<int>  $weeklyOffDays  ISO weekdays (1 = Monday … 7 = Sunday)
     * @return array{present: float, paid_leave: float, unpaid_leave: float, holidays: float, weekly_offs: float, absent: float, not_employed: float, upcoming: float, lop_days: float, overtime_minutes: int}
     */
    public function calculate(
        Staff $staff,
        CarbonImmutable $start,
        CarbonImmutable $end,
        Collection $attendance,
        Collection $leaves,
        array $holidayDates,
        array $weeklyOffDays,
        CarbonImmutable $today,
    ): array {
        $counts = array_fill_keys(['present', 'paid_leave', 'unpaid_leave', 'holidays', 'weekly_offs', 'absent', 'not_employed', 'upcoming'], 0.0);
        $byDate = $attendance->keyBy(fn (Attendance $a) => $a->work_date->toDateString());
        $joined = $staff->joining_date ? CarbonImmutable::parse($staff->joining_date)->startOfDay() : null;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $record = $byDate->get($date);

            if ($joined && $day->lt($joined)) {
                $counts['not_employed']++;

                continue;
            }

            if ($record && (in_array($record->status, self::WORKED, true) || ($record->check_in_at && $record->status !== 'half_day'))) {
                $counts['present']++;

                continue;
            }

            if (in_array($date, $holidayDates, true) || ($record && $record->status === 'holiday')) {
                $counts['holidays']++;

                continue;
            }

            if (in_array($day->isoWeekday(), $weeklyOffDays, true) || ($record && $record->status === 'weekly_off')) {
                $counts['weekly_offs']++;

                continue;
            }

            // Whole day to account for; a half-day attendance covers half of it.
            $remaining = 1.0;
            if ($record && $record->status === 'half_day') {
                $counts['present'] += 0.5;
                $remaining = 0.5;
            }

            $leave = $leaves->first(fn (LeaveRequest $l) => $l->from_date->toDateString() <= $date && $l->to_date->toDateString() >= $date);
            if ($leave) {
                $units = min($leave->partial_day ? 0.5 : 1.0, $remaining);
                $counts[$leave->leaveType?->paid === false ? 'unpaid_leave' : 'paid_leave'] += $units;
                $remaining -= $units;
            } elseif ($record && $record->status === 'on_leave') {
                $counts['paid_leave'] += $remaining;
                $remaining = 0.0;
            }

            if ($remaining > 0) {
                $counts[$day->gt($today) ? 'upcoming' : 'absent'] += $remaining;
            }
        }

        $counts['lop_days'] = $counts['absent'] + $counts['unpaid_leave'] + $counts['not_employed'];
        $counts['overtime_minutes'] = (int) $attendance->sum('overtime_minutes');

        return $counts;
    }
}
