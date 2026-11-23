<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Staff;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);
    }

    /** An employee with an app login and no history (e.g. added by mistake). */
    private function employeeWithLogin(string $code = 'EMP-101'): Staff
    {
        $user = User::factory()->create();
        $user->assignRole('Employee');

        return Staff::create([
            'user_id' => $user->id, 'employee_code' => $code, 'first_name' => 'Asha', 'last_name' => 'Roy',
            'email' => $user->email, 'employment_status' => 'active',
        ]);
    }

    public function test_employee_without_history_is_deleted_with_their_login(): void
    {
        $staff = $this->employeeWithLogin();
        $user = $staff->user;
        $user->createToken('app');
        $report = Staff::create(['employee_code' => 'EMP-102', 'first_name' => 'Report', 'manager_id' => $staff->id]);
        $department = Department::create(['name' => 'Ops', 'code' => 'OPS', 'head_staff_id' => $staff->id]);
        $team = Team::create(['name' => 'App team', 'department_id' => $department->id, 'manager_id' => $staff->id]);

        $this->get(route('admin.staff.edit', $staff))->assertOk()->assertSee('Delete employee');

        $this->delete(route('admin.staff.destroy', $staff))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('success', 'Asha Roy was deleted, along with their app login.');

        $this->assertModelMissing($staff);
        $this->assertModelMissing($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $user->id]);
        $this->assertNull($report->fresh()->manager_id);
        $this->assertNull($department->fresh()->head_staff_id);
        $this->assertNull($team->fresh()->manager_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.deleted']);
    }

    public function test_employee_without_a_login_can_be_deleted(): void
    {
        $staff = Staff::create(['employee_code' => 'EMP-103', 'first_name' => 'No', 'last_name' => 'Login']);

        $this->delete(route('admin.staff.destroy', $staff))->assertSessionHas('success', 'No Login was deleted.');
        $this->assertModelMissing($staff);
    }

    public function test_employee_with_attendance_or_leave_cannot_be_deleted(): void
    {
        $staff = $this->employeeWithLogin();
        Attendance::create(['staff_id' => $staff->id, 'work_date' => '2026-11-20', 'status' => 'present']);
        $leaveType = LeaveType::create(['name' => 'Casual', 'code' => 'CL', 'paid' => true]);
        LeaveRequest::create(['staff_id' => $staff->id, 'leave_type_id' => $leaveType->id, 'from_date' => '2026-11-24', 'to_date' => '2026-11-24', 'total_days' => 1]);

        $this->from(route('admin.staff.index'))->delete(route('admin.staff.destroy', $staff))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('error', "Can't delete Asha Roy: they have 1 attendance day, 1 leave request. Deleting would erase that history, so deactivate them instead.");

        $this->assertModelExists($staff);
        $this->assertModelExists($staff->user);
        $this->get(route('admin.staff.edit', $staff))->assertOk()->assertSee("Can't be deleted", false)->assertDontSee('Delete employee</button>', false);
    }

    public function test_login_that_approved_other_peoples_leave_blocks_deletion(): void
    {
        $approver = $this->employeeWithLogin();
        $requester = Staff::create(['employee_code' => 'EMP-104', 'first_name' => 'Other']);
        $leaveType = LeaveType::create(['name' => 'Casual', 'code' => 'CL', 'paid' => true]);
        $request = LeaveRequest::create(['staff_id' => $requester->id, 'leave_type_id' => $leaveType->id, 'from_date' => '2026-11-24', 'to_date' => '2026-11-24', 'total_days' => 1]);
        DB::table('leave_approvals')->insert(['leave_request_id' => $request->id, 'approver_id' => $approver->user_id, 'decision' => 'approved']);

        $this->delete(route('admin.staff.destroy', $approver))->assertSessionHas('error');

        $this->assertModelExists($approver);
        $this->assertDatabaseCount('leave_approvals', 1);
    }

    public function test_admin_cannot_delete_their_own_employee_record(): void
    {
        $own = Staff::create(['user_id' => $this->admin->id, 'employee_code' => 'EMP-001', 'first_name' => 'Me']);

        $this->delete(route('admin.staff.destroy', $own))->assertSessionHas('error', 'You cannot delete your own account.');
        $this->assertModelExists($own);

        $html = $this->get(route('admin.staff.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('action="'.route('admin.staff.destroy', $own).'"', $html);
    }

    public function test_employee_list_has_a_delete_button(): void
    {
        $staff = $this->employeeWithLogin();

        $this->get(route('admin.staff.index'))->assertOk()
            ->assertSee('action="'.route('admin.staff.destroy', $staff).'"', false)
            ->assertSee('Permanently delete Asha Roy and their app login?');
    }
}
