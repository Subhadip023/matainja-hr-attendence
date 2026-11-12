<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'staff_id', 'leave_type_id', 'year',
        'opening', 'accrued', 'used', 'pending',
    ];

    protected function casts(): array
    {
        return [
            'opening' => 'decimal:1',
            'accrued' => 'decimal:1',
            'used' => 'decimal:1',
            'pending' => 'decimal:1',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function getAvailableAttribute(): float
    {
        return (float) $this->opening + (float) $this->accrued - (float) $this->used - (float) $this->pending;
    }
}
