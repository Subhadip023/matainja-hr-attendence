<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo login accounts (password: password) with staff profiles.
 * Demo-only (see DemoData). Existing users are never changed: a login that
 * already exists keeps its name and password; it only gets its role if missing.
 */
class DemoUserSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['name' => 'Super Admin', 'email' => 'admin@example.com', 'role' => 'Super Admin'],
        ['name' => 'HR Admin', 'email' => 'hr@example.com', 'role' => 'HR Admin'],
        ['name' => 'Manager User', 'email' => 'manager@example.com', 'role' => 'Manager'],
        ['name' => 'Employee User', 'email' => 'employee@example.com', 'role' => 'Employee'],
    ];

    public function run(): void
    {
        if (! DemoData::allowed()) {
            $this->command?->warn(DemoData::skippedMessage('demo login accounts'));

            return;
        }

        $branch = Branch::where('code', 'HQ')->first();
        $ops = Department::where('code', 'OPS')->first();
        $hr = Department::where('code', 'HR')->first();
        $manager = null;

        foreach (self::ACCOUNTS as $account) {
            // One transaction per account: never leave a login without its staff profile.
            $staff = DB::transaction(function () use ($account, $branch, $ops, $hr, $manager) {
                $user = User::firstOrCreate(
                    ['email' => $account['email']],
                    ['name' => $account['name'], 'password' => 'password']
                );
                $user->assignRole($account['role']);

                $parts = explode(' ', $account['name']);

                return Staff::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        // Next free code, so real employees' codes are never clashed with.
                        'employee_code' => Staff::nextEmployeeCode(),
                        'first_name' => $parts[0],
                        'last_name' => $parts[1] ?? '',
                        'name' => $account['name'],
                        'email' => $account['email'],
                        'department_id' => $account['role'] === 'HR Admin' ? $hr?->id : $ops?->id,
                        'branch_id' => $branch?->id,
                        'manager_id' => $manager?->id,
                        'joining_date' => '2026-01-01',
                        'employment_status' => 'active',
                    ]
                );
            });

            if ($account['role'] === 'Manager') {
                $manager = $staff;
            }
        }
    }
}
