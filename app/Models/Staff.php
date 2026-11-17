<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'user_id', 'initial_password', 'employee_code', 'first_name', 'last_name', 'name', 'email',
        'phone', 'position', 'department_id', 'designation_id', 'branch_id',
        'team_id', 'manager_id', 'joining_date', 'employment_status',
    ];

    /**
     * Never serialise the temporary password (e.g. into audit logs); the
     * StaffResource exposes it to admins explicitly.
     *
     * @var list<string>
     */
    protected $hidden = ['initial_password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'initial_password' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Staff::class, 'manager_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(StaffShiftAssignment::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'owner_staff_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->name ?? '');
    }
}
