<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'teams';

    protected $fillable = ['name', 'department_id', 'manager_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'manager_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
