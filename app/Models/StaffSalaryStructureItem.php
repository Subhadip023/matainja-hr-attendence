<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSalaryStructureItem extends Model
{
    protected $fillable = [
        'staff_salary_structure_id', 'salary_component_id', 'name', 'code', 'type',
        'monthly_amount', 'is_taxable', 'is_prorated', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_prorated' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StaffSalaryStructure::class, 'staff_salary_structure_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
