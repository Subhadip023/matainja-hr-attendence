<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRoom extends Model
{
    protected $fillable = ['name', 'branch_id', 'capacity', 'facilities'];

    protected function casts(): array
    {
        return ['facilities' => 'array'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
