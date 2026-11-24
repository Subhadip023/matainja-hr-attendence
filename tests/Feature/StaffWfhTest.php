<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use App\Models\WfhRequest;
use App\Services\AttendanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffWfhTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 10:00:00'); // a Tuesday
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Boss']);
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        $this->staff = Staff::create(['employee_code' => 'EMP-401', 'first_name' => 'Asha', 'last_name' => 'Roy', 'employment_status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function wfh(string $from, string $to, string $status = 'approved'): WfhRequest
    {
        return $this->staff->wfhRequests()->create(['from_date' => $from, 'to_date' => $to, 'status' => $status, 'reason' => 'Test']);
    }

    public function test_admin_adds_approved_work_from_home_that_check_in_honours(): void
    {
        $this->get(route('admin.staff.edit', $this->staff))->assertOk()->assertSee('Add WFH')->assertSee('No work from home yet.');

        $this->post(route('admin.staff.wfh.store', $this->staff), ['from_date' => '2026-11-26', 'to_date' => '2026-11-27', 'reason' => 'Internet installation'])
            ->assertRedirect(route('admin.staff.edit', $this->staff).'#wfh')
            ->assertSessionHas('success', 'Asha Roy can work from home from 26 Nov to 27 Nov 2026.');

        $wfh = $this->staff->wfhRequests()->sole();
        $this->assertSame('approved', $wfh->status);
        $this->assertSame($this->admin->id, $wfh->approver_id);
        $this->assertNotNull($wfh->decided_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wfh.added_by_admin']);

        $attendance = app(AttendanceService::class);
        $this->assertTrue($attendance->isWfhDay($this->staff, '2026-11-27'));
        $this->assertFalse($attendance->isWfhDay($this->staff, '2026-11-30'));

        $this->get(route('admin.staff.edit', $this->staff))->assertSee('Internet installation')->assertSee('Boss (added by admin)');
    }

    public function test_dates_must_start_today_or_later_and_end_after_they_start(): void
    {
        $this->post(route('admin.staff.wfh.store', $this->staff), ['from_date' => '2026-11-23', 'to_date' => '2026-11-24'])
            ->assertSessionHasErrorsIn('wfh', 'from_date');
        $this->post(route('admin.staff.wfh.store', $this->staff), ['from_date' => '2026-11-26', 'to_date' => '2026-11-25'])
            ->assertSessionHasErrorsIn('wfh', 'to_date');

        $this->post(route('admin.staff.wfh.store', $this->staff), ['from_date' => '2026-11-24', 'to_date' => '2026-11-24'])
            ->assertSessionHas('success', 'Asha Roy can work from home on 24 Nov 2026.');
    }

    public function test_overlapping_approved_work_from_home_is_refused(): void
    {
        $this->wfh('2026-11-26', '2026-11-28');
        $this->wfh('2026-11-30', '2026-11-30', 'pending'); // pending requests don't block

        $this->post(route('admin.staff.wfh.store', $this->staff), ['from_date' => '2026-11-27', 'to_date' => '2026-12-01'])
            ->assertSessionHasErrorsIn('wfh', ['from_date' => 'Already working from home from 26 Nov to 28 Nov 2026. Pick other dates or cancel that one first.']);
        $this->post(route('admin.staff.wfh.store', $this->staff), ['from_date' => '2026-11-29', 'to_date' => '2026-11-30'])
            ->assertSessionHas('success');
    }

    public function test_former_employees_get_no_new_work_from_home(): void
    {
        $this->staff->update(['employment_status' => 'resigned']);

        $this->get(route('admin.staff.edit', $this->staff))->assertDontSee('Add WFH');
        $this->post(route('admin.staff.wfh.store', $this->staff), ['from_date' => '2026-11-26', 'to_date' => '2026-11-26'])
            ->assertSessionHas('error', "Asha Roy is resigned; work from home can't be added.");
        $this->assertSame(0, WfhRequest::count());
    }

    public function test_cancelling_upcoming_cancels_it_and_cancelling_one_in_progress_ends_it_yesterday(): void
    {
        $upcoming = $this->wfh('2026-11-26', '2026-11-27');
        $running = $this->wfh('2026-11-20', '2026-11-25');
        $past = $this->wfh('2026-11-10', '2026-11-12');

        $this->post(route('admin.staff.wfh.cancel', [$this->staff, $upcoming]))->assertSessionHas('success');
        $this->assertSame('cancelled', $upcoming->fresh()->status);
        $this->assertFalse(app(AttendanceService::class)->isWfhDay($this->staff, '2026-11-26'));

        $this->post(route('admin.staff.wfh.cancel', [$this->staff, $running]))
            ->assertSessionHas('success', "Work from home for Asha Roy now ends 23 Nov 2026; they're back in the office from today.");
        $this->assertSame('approved', $running->fresh()->status);
        $this->assertSame('2026-11-23', $running->fresh()->to_date->toDateString());

        $this->post(route('admin.staff.wfh.cancel', [$this->staff, $past]))->assertSessionHas('error');
        $this->assertDatabaseHas('audit_logs', ['action' => 'wfh.cancelled_by_admin']);
    }

    public function test_attendance_list_has_a_wfh_button_for_today_that_returns_to_the_list(): void
    {
        $withWfh = Staff::create(['employee_code' => 'EMP-403', 'first_name' => 'Home', 'employment_status' => 'active']);
        $withWfh->wfhRequests()->create(['from_date' => '2026-11-24', 'to_date' => '2026-11-24', 'status' => 'approved']);
        $list = route('admin.attendance.index', ['q' => 'EMP']);

        $this->get($list)->assertOk()
            ->assertSee('data-wfh-action="'.route('admin.staff.wfh.store', $this->staff).'"', false)
            ->assertDontSee('data-wfh-action="'.route('admin.staff.wfh.store', $withWfh).'"', false)
            ->assertSee('WFH approved');

        $this->from($list)->post(route('admin.staff.wfh.store', $this->staff), [
            'from_date' => '2026-11-24', 'to_date' => '2026-11-25', 'return_to' => 'attendance',
        ])->assertRedirect($list)->assertSessionHas('success', 'Asha Roy can work from home from 24 Nov to 25 Nov 2026.');

        $this->assertTrue(app(AttendanceService::class)->isWfhDay($this->staff, '2026-11-24'));
    }

    public function test_a_failed_wfh_from_the_attendance_list_reopens_the_pop_up_for_that_employee(): void
    {
        $list = route('admin.attendance.index');

        $this->from($list)->followingRedirects()->post(route('admin.staff.wfh.store', $this->staff), [
            'from_date' => '2026-11-26', 'to_date' => '2026-11-25', 'return_to' => 'attendance',
            'wfh_staff_id' => $this->staff->id, 'wfh_staff_name' => 'Asha Roy',
        ])->assertOk()
            ->assertSee('data-open-on-load', false)
            ->assertSee('action="'.route('admin.staff.wfh.store', $this->staff).'"', false)
            ->assertSee('The end date must be on or after the start date.');
    }

    public function test_no_wfh_button_on_past_days_or_on_a_wfh_weekday(): void
    {
        $this->get(route('admin.attendance.index', ['date' => '2026-11-23']))->assertOk()->assertDontSee('data-wfh-action', false);

        Carbon::setTestNow('2026-11-25 10:00:00'); // Wednesday: everyone works from home
        $this->get(route('admin.attendance.index'))->assertOk()->assertDontSee('data-wfh-action', false);
    }

    public function test_work_from_home_of_another_employee_cannot_be_cancelled_through_this_one(): void
    {
        $other = Staff::create(['employee_code' => 'EMP-402', 'first_name' => 'Other', 'employment_status' => 'active']);
        $theirs = $other->wfhRequests()->create(['from_date' => '2026-11-26', 'to_date' => '2026-11-26', 'status' => 'approved']);

        $this->post(route('admin.staff.wfh.cancel', [$this->staff, $theirs]))->assertNotFound();
        $this->assertSame('approved', $theirs->fresh()->status);
    }
}
