<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\ExpenseCategory;
use App\Models\LeaveType;
use App\Models\MeetingRoom;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $branch = Branch::firstOrCreate(
            ['code' => 'HQ'],
            ['name' => 'Head Office', 'address' => 'Kolkata', 'timezone' => 'Asia/Kolkata']
        );

        $department = Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operations']);
        $hrDept = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);

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

        // Demo login accounts — password: password
        $accounts = [
            ['name' => 'Super Admin', 'email' => 'admin@example.com', 'role' => 'Super Admin'],
            ['name' => 'HR Admin', 'email' => 'hr@example.com', 'role' => 'HR Admin'],
            ['name' => 'Manager User', 'email' => 'manager@example.com', 'role' => 'Manager'],
            ['name' => 'Employee User', 'email' => 'employee@example.com', 'role' => 'Employee'],
        ];

        $manager = null;
        foreach ($accounts as $i => $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => 'password']
            );
            $user->assignRole($account['role']);

            Staff::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code' => 'EMP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'first_name' => explode(' ', $account['name'])[0],
                    'last_name' => explode(' ', $account['name'])[1] ?? '',
                    'email' => $account['email'],
                    'department_id' => $account['role'] === 'HR Admin' ? $hrDept->id : $department->id,
                    'branch_id' => $branch->id,
                    'manager_id' => $manager?->id,
                    'joining_date' => '2026-01-01',
                    'employment_status' => 'active',
                ]
            );

            if ($account['role'] === 'Manager') {
                $manager = Staff::where('user_id', $user->id)->first();
            }
        }
    }
}
