<?php

namespace App\Services;

/**
 * Annual income tax on salary under the new or old regime, using the
 * slabs in config/payroll.php. Investment declarations (80C beyond PF,
 * HRA exemption, ...) are not taken into account yet.
 */
class IncomeTaxCalculator
{
    /**
     * @param  float  $annualTaxableSalary  taxable earnings for the whole financial year
     * @param  float  $annualEmployeePf  employee PF for the year (old regime 80C)
     * @param  float  $annualProfessionalTax  PT for the year (old regime 16(iii))
     */
    public function annualTax(float $annualTaxableSalary, string $regime, float $annualEmployeePf = 0, float $annualProfessionalTax = 0): float
    {
        $rules = config("payroll.income_tax.regimes.{$regime}") ?? config('payroll.income_tax.regimes.new');

        $taxable = $annualTaxableSalary - $rules['standard_deduction'];
        if ($rules['deduct_pf_and_pt']) {
            $taxable -= min($annualEmployeePf, $rules['section_80c_limit'] ?? $annualEmployeePf) + $annualProfessionalTax;
        }
        $taxable = max(floor($taxable), 0);

        $tax = $this->slabTax($taxable, $rules['slabs']);

        // Section 87A rebate: no tax up to the limit; above it the tax can't exceed the income over the limit.
        $limit = $rules['rebate_income_limit'];
        if ($taxable <= $limit) {
            $tax = 0.0;
        } elseif ($regime === 'new') {
            $tax = min($tax, $taxable - $limit);
        }

        $surchargeRate = min($this->slabRate($taxable, config('payroll.income_tax.surcharge')), $rules['max_surcharge_rate']);
        $tax += $tax * $surchargeRate / 100;
        $tax += $tax * config('payroll.income_tax.cess_rate') / 100;

        return round($tax);
    }

    /** @param  list<array{0: int|float|null, 1: int|float}>  $slabs */
    private function slabTax(float $income, array $slabs): float
    {
        $tax = 0.0;
        $lower = 0.0;
        foreach ($slabs as [$upper, $rate]) {
            $top = $upper === null ? $income : min($income, (float) $upper);
            if ($top > $lower) {
                $tax += ($top - $lower) * $rate / 100;
            }
            if ($upper === null || $income <= $upper) {
                break;
            }
            $lower = (float) $upper;
        }

        return $tax;
    }

    /** @param  list<array{0: int|float|null, 1: int|float}>  $slabs */
    private function slabRate(float $income, array $slabs): float
    {
        foreach ($slabs as [$upper, $rate]) {
            if ($upper === null || $income <= $upper) {
                return (float) $rate;
            }
        }

        return 0.0;
    }
}
