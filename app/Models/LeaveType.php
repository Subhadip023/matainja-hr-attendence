<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['name', 'code', 'paid', 'requires_document', 'max_days'];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'requires_document' => 'boolean',
        ];
    }
}
