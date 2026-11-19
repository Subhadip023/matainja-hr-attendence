<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Staff;
use App\Models\User;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceWfhTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE_LATITUDE = 22.5726;

    private const OFFICE_LONGITUDE = 88.3639;

    /** ~1 km north of the office: outside the 100 m fence. */
    private const HOME = ['latitude' => 22.5816, 'longitude' => 88.3639, 'accuracy_meters' => 12];

    private const WEDNESDAY = '2026-11-18 10:00:00';

    private const THURSDAY = '2026-11-19 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function employee(): Staff
    {
        $branch = Branch::create([
            'name' => 'Kolkata HQ',
            'code' => 'KOL',
            'latitude' => self::OFFICE_LATITUDE,
            'longitude' => self::OFFICE_LONGITUDE,
            'geofence_radius_meters' => 100,
        ]);
        $user = User::factory()->create();
        $user->assignRole('Employee');
        $staff = Staff::create([
            'user_id' => $user->id,
            'first_name' => 'Riya',
            'last_name' => 'Sen',
            'email' => $user->email,
            'branch_id' => $branch->id,
        ]);
        Sanctum::actingAs($user);

        return $staff;
    }

    public function test_wednesday_check_in_and_out_from_home_is_wfh(): void
    {
        $this->travelTo(Carbon::parse(self::WEDNESDAY));
        $this->employee();

        $this->postJson('/api/v1/attendance/check-in', self::HOME)
            ->assertCreated()
            ->assertJsonPath('data.status', 'wfh')
            ->assertJsonPath('data.work_mode', 'wfh')
            ->assertJsonPath('data.check_in_location.accuracy_meters', 12);

        $this->travel(8)->hours();

        $this->postJson('/api/v1/attendance/check-out', self::HOME)->assertOk();

        $this->assertDatabaseHas('attendance', ['status' => 'wfh']);
        $this->assertDatabaseCount('attendance_events', 2);
    }

    public function test_wednesday_check_in_works_without_location(): void
    {
        $this->travelTo(Carbon::parse(self::WEDNESDAY));
        $this->employee();

        $this->postJson('/api/v1/attendance/check-in')->assertCreated()->assertJsonPath('data.status', 'wfh');
    }

    public function test_other_days_still_require_the_office(): void
    {
        $this->travelTo(Carbon::parse(self::THURSDAY));
        $this->employee();

        $this->postJson('/api/v1/attendance/check-in', self::HOME)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_in');
    }

    public function test_approved_wfh_request_allows_remote_check_in(): void
    {
        $this->travelTo(Carbon::parse(self::THURSDAY));
        $staff = $this->employee();
        WfhRequest::create([
            'staff_id' => $staff->id,
            'from_date' => '2026-11-19',
            'to_date' => '2026-11-19',
            'reason' => 'Plumber visit',
            'status' => 'approved',
        ]);

        $this->postJson('/api/v1/attendance/check-in', self::HOME)
            ->assertCreated()
            ->assertJsonPath('data.status', 'wfh');
    }

    public function test_pending_wfh_request_does_not_count(): void
    {
        $this->travelTo(Carbon::parse(self::THURSDAY));
        $staff = $this->employee();
        WfhRequest::create([
            'staff_id' => $staff->id,
            'from_date' => '2026-11-19',
            'to_date' => '2026-11-19',
            'reason' => 'Plumber visit',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/attendance/check-in', self::HOME)->assertUnprocessable();
    }

    public function test_wfh_weekdays_setting_changes_the_day(): void
    {
        CompanySetting::create(['key' => 'attendance.wfh_weekdays', 'value' => '4', 'data_type' => 'string']);
        $this->travelTo(Carbon::parse(self::WEDNESDAY));
        $this->employee();

        $this->postJson('/api/v1/attendance/check-in', self::HOME)->assertUnprocessable();
    }

    public function test_attendance_list_shows_punch_locations_and_distance(): void
    {
        $this->travelTo(Carbon::parse(self::WEDNESDAY));
        $this->employee();
        $this->postJson('/api/v1/attendance/check-in', self::HOME)->assertCreated();

        $admin = User::factory()->create();
        $admin->assignRole('HR Admin');
        Sanctum::actingAs($admin);

        $row = $this->getJson('/api/v1/attendance')->assertOk()->json('data.0');

        $this->assertSame('wfh', $row['work_mode']);
        $this->assertEqualsWithDelta(self::HOME['latitude'], $row['check_in_location']['latitude'], 0.00001);
        $this->assertSame('Kolkata HQ', $row['check_in_location']['office_name']);
        $this->assertEqualsWithDelta(1000, $row['check_in_location']['distance_from_office_meters'], 20);
        $this->assertNull($row['check_out_location']);
    }
}
