<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\ExpenseCategory;
use App\Models\LeaveType;
use App\Models\MeetingRoom;
use App\Models\Shift;
use App\Models\TicketCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Reference data (roles, branch, departments, leave types, taxes, ...) is
     * safe to run on any server: it only adds what is missing. Demo logins and
     * demo invoices are added only in local/testing (see DemoData).
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $branch = Branch::firstOrCreate(
            ['code' => 'HQ'],
            ['name' => 'Head Office', 'address' => 'Kolkata', 'timezone' => 'Asia/Kolkata']
        );

        Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operations']);
        Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);

        Designation::firstOrCreate(['title' => 'Office Manager'], ['level' => 5]);
        Designation::firstOrCreate(['title' => 'Executive'], ['level' => 2]);

        Shift::firstOrCreate(
            ['name' => 'General Shift'],
            ['start_time' => '09:30', 'end_time' => '18:30', 'grace_minutes' => 15]
        );

        foreach ([
            ['name' => 'Casual Leave', 'code' => 'CL', 'paid' => true, 'max_days' => 12],
            ['name' => 'Sick Leave', 'code' => 'SL', 'paid' => true, 'requires_document' => true, 'max_days' => 10],
            ['name' => 'Earned Leave', 'code' => 'EL', 'paid' => true, 'max_days' => 15],
            ['name' => 'Loss of Pay', 'code' => 'LOP', 'paid' => false],
        ] as $type) {
            LeaveType::firstOrCreate(['code' => $type['code']], $type);
        }

        foreach (['Travel', 'Meals', 'Office Supplies', 'Accommodation'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }

        foreach (['IT Support', 'HR', 'Facilities', 'Finance'] as $name) {
            TicketCategory::firstOrCreate(['name' => $name]);
        }

        MeetingRoom::firstOrCreate(
            ['name' => 'Conference Room A'],
            ['branch_id' => $branch->id, 'capacity' => 10]
        );

        // Company details for invoices (only empty values are filled) and GST rates.
        $this->call(CompanySettingSeeder::class);
        $this->call(TaxSeeder::class);
        $this->call(SalaryComponentSeeder::class);

        // Demo logins (password: password) and demo clients/invoices.
        // Both skip themselves on live servers — see DemoData.
        $this->call(DemoUserSeeder::class);
        $this->call(InvoiceSeeder::class);
        $this->call(PayrollDemoSeeder::class);
    }
}
