<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUSES = ['draft', 'sent', 'paid', 'cancelled'];

    protected $fillable = [
        'client_id', 'number', 'issue_date', 'due_date', 'currency', 'payment_mode', 'status',
        'subtotal', 'discount', 'tax_total', 'commission_fee', 'total', 'paid_at', 'payment_reference',
        'notes', 'terms', 'created_by',
    ];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'paid_at' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'commission_fee' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /** Taxes picked for this invoice (CGST, SGST, IGST, ...). */
    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Paid and cancelled invoices are final and can't be edited. */
    public function isLocked(): bool
    {
        return in_array($this->status, ['paid', 'cancelled'], true);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'sent' && $this->due_date !== null
            && $this->due_date->lt(CarbonImmutable::today());
    }
}
