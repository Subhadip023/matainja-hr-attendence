<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Services\InvoiceService;
use Illuminate\Database\Seeder;

/**
 * Company details printed on invoices, plus invoice defaults.
 * Placeholder values: change them under Admin → Settings → Invoicing.
 * Never overwrites a value that is already filled in.
 */
class CompanySettingSeeder extends Seeder
{
    public const DEFAULT_TERMS = <<<'TXT'
        Payment due within 15 days of the invoice date.
        Please quote the invoice number with your payment.

        Bank: State Bank of India
        Account name: Matainja Technologies Pvt. Ltd.
        Account no.: 000000000000
        IFSC: SBIN0000000
        UPI: matainja@sbi

        Late payments may attract interest at 1.5% per month.
        TXT;

    public function run(): void
    {
        $values = [
            'company.name' => 'Matainja Technologies Pvt. Ltd.',
            'company.address' => "Sector V, Salt Lake\nKolkata, West Bengal 700091\nIndia",
            'company.tax_id' => '19AAAAA0000A1Z5',
            'company.email' => 'accounts@matainja.com',
            'company.phone' => '+91 33 0000 0000',
            InvoiceService::SETTING_PREFIX => 'INV',
            InvoiceService::SETTING_DUE_DAYS => '15',
            InvoiceService::SETTING_TERMS => self::DEFAULT_TERMS,
        ];

        foreach ($values as $key => $value) {
            $setting = CompanySetting::firstOrNew(['key' => $key]);
            if (blank($setting->value)) {
                $setting->fill(['value' => $value, 'data_type' => 'string'])->save();
            }
        }
    }
}
