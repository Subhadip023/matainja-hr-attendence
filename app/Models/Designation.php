<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'designations';

    protected $fillable = ['title', 'level'];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
