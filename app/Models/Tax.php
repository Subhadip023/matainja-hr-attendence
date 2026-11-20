<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    protected $fillable = ['name', 'rate', 'description', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function invoiceTaxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class);
    }

    /** "CGST @ 9%" */
    public function getLabelAttribute(): string
    {
        return $this->name.' @ '.self::formatRate($this->rate).'%';
    }

    public static function formatRate(mixed $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
    }
}
