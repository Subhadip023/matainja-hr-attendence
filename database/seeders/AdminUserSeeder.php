<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    /**
     * Create/update the admin login account and linked staff profile.
     * Override credentials via env: ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $name = env('ADMIN_NAME', 'Super Admin');
        $password = env('ADMIN_PASSWORD', 'password');

        DB::transaction(function () use ($email, $name, $password) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password]
            );

            $user->assignRole('Super Admin');

            Staff::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code' => 'EMP-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                    'first_name' => explode(' ', $name)[0],
                    'last_name' => explode(' ', $name)[1] ?? '',
                    'email' => $email,
                    'employment_status' => 'active',
                ]
            );
        });

        $this->command->info("Admin ready: {$email}");
    }
}
