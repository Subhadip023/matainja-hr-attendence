<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportRequest extends Model
{
    protected $fillable = [
        'user_id', 'report_type', 'format', 'filters', 'status', 'file_path',
    ];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
