<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

/**
 * Standard Indian GST rates. CGST 9% + SGST 9% are ticked on new invoices
 * (sales inside your own state). Safe to run again: existing taxes are kept as they are.
 */
class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['name' => 'CGST', 'rate' => 9, 'is_default' => true, 'description' => 'Central GST – sales within the same state'],
            ['name' => 'SGST', 'rate' => 9, 'is_default' => true, 'description' => 'State GST – sales within the same state'],
            ['name' => 'IGST', 'rate' => 18, 'description' => 'Integrated GST – sales to another state'],
            ['name' => 'CGST', 'rate' => 2.5, 'description' => 'Central GST 2.5% (5% slab, same state)'],
            ['name' => 'SGST', 'rate' => 2.5, 'description' => 'State GST 2.5% (5% slab, same state)'],
            ['name' => 'CGST', 'rate' => 6, 'description' => 'Central GST 6% (12% slab, same state)'],
            ['name' => 'SGST', 'rate' => 6, 'description' => 'State GST 6% (12% slab, same state)'],
            ['name' => 'IGST', 'rate' => 5, 'description' => 'Integrated GST 5% slab – another state'],
            ['name' => 'IGST', 'rate' => 12, 'description' => 'Integrated GST 12% slab – another state'],
            ['name' => 'IGST', 'rate' => 28, 'description' => 'Integrated GST 28% slab – another state', 'is_active' => false],
        ];

        foreach ($taxes as $tax) {
            Tax::firstOrCreate(
                ['name' => $tax['name'], 'rate' => $tax['rate']],
                $tax + ['is_default' => false, 'is_active' => true],
            );
        }
    }
}
