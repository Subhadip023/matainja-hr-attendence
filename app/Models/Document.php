<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'owner_staff_id', 'title', 'category', 'file_path',
        'visibility', 'uploaded_by',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'owner_staff_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
