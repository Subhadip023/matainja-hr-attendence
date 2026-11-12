<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const TRANSITIONS = [
        'open' => ['assigned', 'closed'],
        'assigned' => ['in_progress', 'closed'],
        'in_progress' => ['waiting_for_employee', 'resolved', 'closed'],
        'waiting_for_employee' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
    ];

    protected $fillable = [
        'ticket_number', 'staff_id', 'category_id', 'subject', 'description',
        'priority', 'status', 'assigned_to',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
