<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceGeofenceTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE_LATITUDE = 22.5726;

    private const OFFICE_LONGITUDE = 88.3639;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function employeeAt(?Branch $branch): User
    {
        $user = User::factory()->create();
        $user->assignRole('Employee');
        Staff::create([
            'user_id' => $user->id,
            'first_name' => 'Riya',
            'last_name' => 'Sen',
            'email' => $user->email,
            'branch_id' => $branch?->id,
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function fencedBranch(array $overrides = []): Branch
    {
        return Branch::create(array_merge([
            'name' => 'Kolkata HQ',
            'code' => 'KOL',
            'latitude' => self::OFFICE_LATITUDE,
            'longitude' => self::OFFICE_LONGITUDE,
            'geofence_radius_meters' => 100,
        ], $overrides));
    }

    public function test_check_in_inside_the_geofence_succeeds(): void
    {
        $this->employeeAt($this->fencedBranch());

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE + 0.0003, // ~33 m
            'longitude' => self::OFFICE_LONGITUDE,
            'accuracy_meters' => 10,
        ])->assertCreated();

        $this->assertDatabaseHas('attendance_events', ['event_type' => 'check_in']);
    }

    public function test_check_in_outside_the_geofence_is_rejected(): void
    {
        $this->employeeAt($this->fencedBranch());

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE + 0.009, // ~1 km
            'longitude' => self::OFFICE_LONGITUDE,
            'accuracy_meters' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('check_in');

        $this->assertDatabaseCount('attendance', 0);
    }

    public function test_check_in_without_location_is_rejected_for_a_fenced_branch(): void
    {
        $this->employeeAt($this->fencedBranch());

        $this->postJson('/api/v1/attendance/check-in')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_in');
    }

    public function test_gps_accuracy_allowance_is_capped(): void
    {
        $this->employeeAt($this->fencedBranch());

        // ~167 m away with a claimed 5 km accuracy: only 50 m of slack is allowed.
        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE + 0.0015,
            'longitude' => self::OFFICE_LONGITUDE,
            'accuracy_meters' => 5000,
        ])->assertJsonValidationErrors('check_in');

        // ~133 m away with 40 m accuracy: within 100 + 40.
        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE + 0.0012,
            'longitude' => self::OFFICE_LONGITUDE,
            'accuracy_meters' => 40,
        ])->assertCreated();
    }

    public function test_check_out_outside_the_geofence_is_rejected(): void
    {
        $this->employeeAt($this->fencedBranch());

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE,
            'longitude' => self::OFFICE_LONGITUDE,
        ])->assertCreated();

        $this->postJson('/api/v1/attendance/check-out', [
            'latitude' => self::OFFICE_LATITUDE + 0.009,
            'longitude' => self::OFFICE_LONGITUDE,
        ])->assertJsonValidationErrors('check_out');
    }

    public function test_branch_without_coordinates_is_not_fenced(): void
    {
        $this->employeeAt(Branch::create(['name' => 'Remote', 'code' => 'REM']));

        $this->postJson('/api/v1/attendance/check-in')->assertCreated();
    }

    public function test_geofence_can_be_switched_off_by_setting(): void
    {
        CompanySetting::create(['key' => 'attendance.geofence_enforced', 'value' => 'false', 'data_type' => 'string']);
        $this->employeeAt($this->fencedBranch());

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE + 0.009,
            'longitude' => self::OFFICE_LONGITUDE,
        ])->assertCreated();
    }

    public function test_disabling_geofence_through_the_settings_api_round_trips(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'settings' => [['key' => 'attendance.geofence_enforced', 'value' => false]],
        ])->assertOk()->assertJsonPath('data', ['attendance.geofence_enforced' => 'false']);

        $this->employeeAt($this->fencedBranch());

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE + 0.009,
            'longitude' => self::OFFICE_LONGITUDE,
        ])->assertCreated();
    }

    public function test_default_radius_setting_applies_when_branch_has_none(): void
    {
        CompanySetting::create(['key' => 'attendance.geofence_radius_meters', 'value' => '2000', 'data_type' => 'string']);
        $this->employeeAt($this->fencedBranch(['geofence_radius_meters' => null]));

        $this->postJson('/api/v1/attendance/check-in', [
            'latitude' => self::OFFICE_LATITUDE + 0.009, // ~1 km, inside 2 km
            'longitude' => self::OFFICE_LONGITUDE,
        ])->assertCreated();
    }
}
