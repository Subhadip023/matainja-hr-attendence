<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseClaim extends Model
{
    protected $fillable = [
        'staff_id', 'category_id', 'amount', 'currency', 'expense_date',
        'description', 'receipt_path', 'status', 'paid_at', 'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class);
    }
}
