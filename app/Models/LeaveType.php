<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;

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
}
