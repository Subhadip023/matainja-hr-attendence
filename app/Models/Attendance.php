<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'staff_id', 'work_date', 'shift_id', 'check_in_at', 'check_out_at',
        'status', 'late_minutes', 'worked_minutes', 'overtime_minutes', 'source',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakRecord::class);
    }

    public function openBreak(): HasOne
    {
        return $this->hasOne(BreakRecord::class)->whereNull('ended_at')->latestOfMany('started_at');
    }
}
