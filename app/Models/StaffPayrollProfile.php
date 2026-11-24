<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Statutory (PF / ESI / PT / income tax) and bank details used by payroll.
 */
class StaffPayrollProfile extends Model
{
    public const TAX_REGIMES = ['new' => 'New regime', 'old' => 'Old regime'];

    protected $fillable = [
        'staff_id', 'pan', 'uan', 'pf_number', 'esi_number',
        'pf_applicable', 'pf_on_full_basic', 'esi_applicable', 'pt_applicable', 'tax_regime',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_ifsc',
    ];

    /**
     * Keep the account number out of audit logs and JSON.
     *
     * @var list<string>
     */
    protected $hidden = ['bank_account_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pf_applicable' => 'boolean',
            'pf_on_full_basic' => 'boolean',
            'esi_applicable' => 'boolean',
            'pt_applicable' => 'boolean',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** "XXXXXX4321" so the full number is only shown in the edit form. */
    public function getMaskedAccountNumberAttribute(): ?string
    {
        $number = (string) $this->bank_account_number;

        return $number === '' ? null : str_repeat('X', max(strlen($number) - 4, 0)).substr($number, -4);
    }
}
