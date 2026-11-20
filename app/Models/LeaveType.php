<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'leave_types';

    protected $fillable = ['name', 'code', 'paid', 'requires_document', 'max_days'];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'requires_document' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
