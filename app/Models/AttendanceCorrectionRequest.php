<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'staff_id', 'attendance_id', 'work_date', 'requested_values',
        'reason', 'status', 'reviewer_id', 'review_comment', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'requested_values' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
