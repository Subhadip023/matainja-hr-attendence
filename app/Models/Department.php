<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'departments';

    protected $fillable = ['name', 'code', 'head_staff_id'];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'head_staff_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
