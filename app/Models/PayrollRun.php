<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month's payroll: draft (can be recalculated and adjusted) → locked
 * (final figures) → paid.
 */
class PayrollRun extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'locked' => 'Locked', 'paid' => 'Paid'];

    protected $fillable = [
        'year', 'month', 'period_start', 'period_end', 'status', 'employee_count',
        'total_gross', 'total_deductions', 'total_net', 'total_employer_contributions', 'skipped',
        'processed_at', 'processed_by', 'locked_at', 'locked_by', 'paid_on', 'payment_reference',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_employer_contributions' => 'decimal:2',
            'skipped' => 'array',
            'processed_at' => 'datetime',
            'locked_at' => 'datetime',
            'paid_on' => 'date',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** "November 2026" */
    public function getLabelAttribute(): string
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->format('F Y');
    }

    /** First month of the financial year this run belongs to, e.g. 2026-04-01 for Nov 2026. */
    public function financialYearStart(): CarbonImmutable
    {
        $startMonth = (int) config('payroll.financial_year_start_month', 4);
        $year = $this->month >= $startMonth ? $this->year : $this->year - 1;

        return CarbonImmutable::create($year, $startMonth, 1);
    }
}
