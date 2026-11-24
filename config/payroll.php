<?php

/*
| Indian statutory payroll rules. Rates change with each Budget / state
| notification: update them here. Slabs are [upper limit, value] pairs in
| ascending order; a null upper limit means "and above".
*/

return [

    // Financial year starts in April (used for TDS year-to-date).
    'financial_year_start_month' => 4,

    // Employees' Provident Fund. PF wage = earned Basic, capped at the
    // ceiling unless the employee opts for PF on full Basic.
    'pf' => [
        'employee_rate' => 12,
        'employer_rate' => 12,
        'eps_rate' => 8.33,       // employer share going to pension, always on the capped wage
        'wage_ceiling' => 15000,
    ],

    // Employees' State Insurance: only while the monthly gross is within the limit.
    'esi' => [
        'employee_rate' => 0.75,
        'employer_rate' => 3.25,
        'wage_limit' => 21000,
    ],

    // Professional Tax: monthly slabs on gross earnings, by state.
    'professional_tax' => [
        'default_state' => 'WB',
        'states' => [
            'WB' => [
                'name' => 'West Bengal',
                'slabs' => [[10000, 0], [15000, 110], [25000, 130], [40000, 150], [null, 200]],
            ],
        ],
    ],

    // Income tax (TDS on salary), FY 2026-27. Slab values are percentages.
    'income_tax' => [
        'cess_rate' => 4,
        'regimes' => [
            'new' => [
                'standard_deduction' => 75000,
                'rebate_income_limit' => 1200000, // no tax up to this taxable income, with marginal relief above it
                'slabs' => [[400000, 0], [800000, 5], [1200000, 10], [1600000, 15], [2000000, 20], [2400000, 25], [null, 30]],
                'max_surcharge_rate' => 25,
                'deduct_pf_and_pt' => false,
            ],
            'old' => [
                'standard_deduction' => 50000,
                'rebate_income_limit' => 500000,
                'slabs' => [[250000, 0], [500000, 5], [1000000, 20], [null, 30]],
                'max_surcharge_rate' => 37,
                'deduct_pf_and_pt' => true,      // employee PF under 80C (capped) and PT under 16(iii)
                'section_80c_limit' => 150000,
            ],
        ],
        // Surcharge on tax by taxable income (no marginal relief applied).
        'surcharge' => [[5000000, 0], [10000000, 10], [20000000, 15], [50000000, 25], [null, 37]],
    ],

    // Hours in a working day, used to turn overtime minutes into pay.
    'overtime_hours_per_day' => 8,

];
