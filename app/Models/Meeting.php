<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $fillable = [
        'organiser_id', 'title', 'starts_at', 'ends_at',
        'room_id', 'meeting_url', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function organiser(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'organiser_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'room_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('ends_at', '>=', now());
    }
}
