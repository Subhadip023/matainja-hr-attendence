<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'holidays';

    protected $fillable = ['branch_id', 'holiday_date', 'name', 'type'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
