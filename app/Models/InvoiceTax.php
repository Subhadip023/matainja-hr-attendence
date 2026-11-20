<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A tax applied to one invoice, with its own copy of the name and rate. */
class InvoiceTax extends Model
{
    protected $fillable = ['tax_id', 'name', 'rate', 'amount'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->name.' @ '.Tax::formatRate($this->rate).'%';
    }
}
