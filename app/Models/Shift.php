<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'shifts';

    protected $fillable = [
        'name', 'start_time', 'end_time', 'grace_minutes',
        'break_policy', 'crosses_midnight',
    ];

    protected function casts(): array
    {
        return ['crosses_midnight' => 'boolean'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StaffShiftAssignment::class);
    }
}
