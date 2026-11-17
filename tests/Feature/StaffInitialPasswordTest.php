<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffInitialPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createStaffWithLogin(): Staff
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/staff', [
            'employee_code' => 'EMP-1001',
            'first_name' => 'Arjun',
            'last_name' => 'Mehta',
            'email' => 'arjun@example.com',
            'create_login' => true,
            'role' => 'Employee',
            'password' => 'Temp@12345',
        ])->assertCreated()->assertJsonPath('data.initial_password', 'Temp@12345');

        return Staff::where('email', 'arjun@example.com')->firstOrFail();
    }

    public function test_initial_password_is_encrypted_at_rest(): void
    {
        $staff = $this->createStaffWithLogin();

        $raw = DB::table('staff')->where('id', $staff->id)->value('initial_password');

        $this->assertNotSame('Temp@12345', $raw);
        $this->assertSame('Temp@12345', $staff->initial_password);
    }

    public function test_initial_password_is_not_written_to_the_audit_log(): void
    {
        $this->createStaffWithLogin();

        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('Temp@12345', json_encode($log->toArray()));
        }
    }

    public function test_initial_password_is_hidden_from_non_admins(): void
    {
        $staff = $this->createStaffWithLogin();

        Sanctum::actingAs($staff->user);

        $this->getJson("/api/v1/staff/{$staff->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.initial_password');
    }

    public function test_changing_password_clears_the_initial_password(): void
    {
        $staff = $this->createStaffWithLogin();

        Sanctum::actingAs($staff->user);
        $staff->user->createToken('test');
        Sanctum::actingAs($staff->user->fresh());

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Temp@12345',
            'password' => 'NewSecret@2026',
            'password_confirmation' => 'NewSecret@2026',
        ]);

        $this->assertNull($staff->fresh()->initial_password);
    }
}
