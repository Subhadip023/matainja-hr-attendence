<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Designation;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StaffImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** Same shape and messiness as a Google Form export. */
    private const CSV = <<<'CSV'
First name ,Last name ,Work email,Phone No.,Employee code *,Joining date,Designation,Job title
Santanu,Paul,santanu@example.com,7044663186,M-134,19.06.2025,Web Developer,Developer
Nilrudra,Dutta,nilrudra@example.com,9830676135,"JANI NA, ID CARD PAI NI",2026-03-04,Backend developer,Backend Web Developer
Sudipta ,Mondal,SUDIPTA@example.com,97497 61669,00123,9th October 2026,Executive,Digital marketing Executive
MAHADEV ,JANA,mahadev@example.com,9732179260,M-107,17/12/22,Web Developer,FRONTEND DEVELOPER
SOURAV,BISWAS,sourav@example.com,9635559940,Null,18/10/2022,web developer,Senior Web Developer
Asif,Ali,santanu@example.com,8240835756,N/A,2024-03-04,App Developer,Mobile App Developer
CSV;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);
        Designation::create(['title' => 'Executive']);
    }

    /** @param  array<string, mixed>  $options */
    private function upload(string $csv = self::CSV, array $options = []): TestResponse
    {
        return $this->post(route('admin.staff.import.upload'), array_merge([
            'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
            'create_logins' => '1',
            'role' => 'Employee',
            'employment_status' => 'active',
        ], $options));
    }

    public function test_preview_cleans_the_rows_without_saving_anything(): void
    {
        $this->upload()->assertRedirect(route('admin.staff.import.preview'));

        $this->get(route('admin.staff.import.preview'))->assertOk()
            ->assertSee('5</strong> ready to import', false)
            ->assertSee('Mahadev')
            ->assertSee('&quot;JANI NA, ID CARD PAI NI&quot; isn&#039;t a usable employee code, so it is left blank.', false)
            ->assertSee('santanu@example.com is repeated (also on line 2).')
            ->assertSee('New designation &quot;Web Developer&quot; will be created.', false);

        $this->assertDatabaseCount('staff', 0);
        $this->assertSame(1, Designation::count());
    }

    public function test_import_creates_employees_logins_designations_and_shift(): void
    {
        $branch = Branch::create(['name' => 'Head Office', 'code' => 'HO']);
        $shift = Shift::create(['name' => 'General', 'start_time' => '09:00', 'end_time' => '18:00']);

        $this->upload(options: ['branch_id' => $branch->id, 'shift_id' => $shift->id]);
        $this->post(route('admin.staff.import.store'))->assertRedirect(route('admin.staff.import.result'));

        $this->assertSame(5, Staff::count());

        $mahadev = Staff::where('email', 'mahadev@example.com')->first();
        $this->assertSame('Mahadev', $mahadev->first_name);
        $this->assertSame('Jana', $mahadev->last_name);
        $this->assertSame('M-107', $mahadev->employee_code);
        $this->assertSame('2022-12-17', $mahadev->joining_date->toDateString());
        $this->assertSame('Frontend Developer', $mahadev->position);
        $this->assertSame($branch->id, $mahadev->branch_id);
        $this->assertTrue($mahadev->user->hasRole('Employee'));
        $this->assertSame($shift->id, $mahadev->shiftAssignments()->value('shift_id'));

        $sudipta = Staff::where('email', 'sudipta@example.com')->first();
        $this->assertSame('00123', $sudipta->employee_code);
        $this->assertSame('9749761669', $sudipta->phone);
        $this->assertSame('2026-10-09', $sudipta->joining_date->toDateString());
        $this->assertSame('Executive', $sudipta->designation->title);

        $this->assertNull(Staff::where('email', 'nilrudra@example.com')->value('employee_code'));
        $this->assertNull(Staff::where('email', 'sourav@example.com')->value('employee_code'));
        $this->assertSame('2025-06-19', Staff::where('email', 'santanu@example.com')->first()->joining_date->toDateString());

        // "Web Developer" and "web developer" become one; the skipped row's "App Developer" isn't created.
        $this->assertSame(['Backend developer', 'Executive', 'Web Developer'], Designation::orderBy('title')->pluck('title')->all());
        $this->assertDatabaseMissing('staff', ['first_name' => 'Asif']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.imported']);

        $this->get(route('admin.staff.import.result'))->assertOk()
            ->assertSee('5</strong> created', false)
            ->assertSee('santanu@example.com is repeated');

        $logins = $this->get(route('admin.staff.import.logins'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Mahadev Jana",M-107,mahadev@example.com,matainja_', $logins);
    }

    public function test_rows_whose_email_or_code_already_exists_are_skipped(): void
    {
        Staff::create(['employee_code' => 'M-134', 'first_name' => 'Old', 'email' => 'old@example.com']);
        User::factory()->create(['email' => 'mahadev@example.com']);

        $this->upload();
        $this->post(route('admin.staff.import.store'));

        $this->assertDatabaseMissing('staff', ['email' => 'santanu@example.com']);
        $this->assertDatabaseMissing('staff', ['email' => 'mahadev@example.com']);
        $this->assertSame(4, Staff::count());
        $this->get(route('admin.staff.import.result'))->assertOk()
            ->assertSee('Employee code M-134 is already used by another employee.')
            ->assertSee('mahadev@example.com already belongs to an employee or login.');
    }

    public function test_import_without_logins_creates_no_users(): void
    {
        $users = User::count();

        $this->upload(options: ['create_logins' => '0']);
        $this->post(route('admin.staff.import.store'));

        $this->assertSame(5, Staff::whereNull('user_id')->count());
        $this->assertSame($users, User::count());
        $this->get(route('admin.staff.import.logins'))->assertRedirect(route('admin.staff.index'));
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $this->from(route('admin.staff.import'))
            ->upload("Name,Phone\nAsha,123\n")
            ->assertRedirect(route('admin.staff.import'))
            ->assertSessionHas('error');

        $this->assertNull(session('staff_import'));
    }

    public function test_confirm_without_a_preview_does_nothing(): void
    {
        $this->post(route('admin.staff.import.store'))->assertRedirect(route('admin.staff.import'));
        $this->assertDatabaseCount('staff', 0);
    }

    public function test_pages_and_template_render(): void
    {
        $this->get(route('admin.staff.index'))->assertOk()->assertSee(route('admin.staff.import'), false);
        $this->get(route('admin.staff.import'))->assertOk()->assertSee('Import employees from CSV');
        $this->assertStringStartsWith('"First name","Last name","Work email"', $this->get(route('admin.staff.import.template'))->streamedContent());
    }
}
