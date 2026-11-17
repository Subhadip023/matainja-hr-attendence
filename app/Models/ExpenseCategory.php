<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'expense_categories';

    protected $fillable = ['name', 'requires_receipt'];

    protected function casts(): array
    {
        return ['requires_receipt' => 'boolean'];
    }
}
