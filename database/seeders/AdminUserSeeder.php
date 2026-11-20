<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Create the Super Admin login (and its staff profile), or give an existing
 * account the Super Admin role. Credentials come from .env:
 * ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
 *
 * Safe for live servers:
 *  - outside local/testing it refuses to run without ADMIN_PASSWORD (never "password");
 *  - an existing account keeps its name and password unless ADMIN_PASSWORD is set,
 *    which is then the way to reset it on purpose;
 *  - the staff profile gets the next free employee code.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $name = env('ADMIN_NAME', 'Super Admin');
        $password = env('ADMIN_PASSWORD');

        if (blank($password) && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Set ADMIN_PASSWORD in .env before seeding the admin on this server (the default "password" is only used locally).');
        }

        $created = DB::transaction(function () use ($email, $name, $password) {
            $user = User::where('email', $email)->first();
            $created = ! $user;

            if ($created) {
                $user = User::create(['email' => $email, 'name' => $name, 'password' => $password ?: 'password']);
            } elseif (filled($password)) {
                $user->update(['password' => $password]);
                $user->tokens()->delete(); // signed out of the app after a reset
            }

            $user->assignRole('Super Admin');

            $parts = explode(' ', $name);
            Staff::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code' => Staff::nextEmployeeCode(),
                    'first_name' => $parts[0],
                    'last_name' => $parts[1] ?? '',
                    'name' => $user->name,
                    'email' => $email,
                    'employment_status' => 'active',
                ]
            );

            return $created;
        });

        $this->command?->info(match (true) {
            $created => "Admin created: {$email}",
            filled($password) => "Admin ready: {$email} (password reset from ADMIN_PASSWORD)",
            default => "Admin ready: {$email} (existing password kept)",
        });
    }
}
