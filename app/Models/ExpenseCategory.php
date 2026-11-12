<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'requires_receipt'];

    protected function casts(): array
    {
        return ['requires_receipt' => 'boolean'];
    }
}
