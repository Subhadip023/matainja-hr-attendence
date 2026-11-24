<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One salary revision of an employee, valid from effective_from until the
 * next revision starts.
 */
class StaffSalaryStructure extends Model
{
    protected $fillable = ['staff_id', 'effective_from', 'monthly_gross', 'notes', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'monthly_gross' => 'decimal:2',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StaffSalaryStructureItem::class)->orderByRaw("CASE WHEN type = 'earning' THEN 0 ELSE 1 END")->orderBy('sort_order');
    }

    /** Fixed monthly deductions agreed in this structure (statutory ones come from the payroll run). */
    public function monthlyDeductions(): float
    {
        return round((float) $this->items->where('type', 'deduction')->sum('monthly_amount'), 2);
    }
}
