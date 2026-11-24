<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffSalaryStructure;
use App\Models\Tax;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\InvoiceSeeder;
use Database\Seeders\PayrollDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const ENV_KEYS = ['ADMIN_PASSWORD', 'ADMIN_EMAIL', 'ADMIN_NAME', 'SEED_DEMO_DATA'];

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            $this->setEnv($key, null);
        }
        parent::tearDown();
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    /** Like on a real server: production needs --force to skip the "are you sure?" prompt. */
    private function seedForced(string $class): void
    {
        $this->artisan('db:seed', ['--class' => $class, '--force' => true])->run();
    }

    private function onLiveServer(): void
    {
        $this->app['env'] = 'production';
    }

    /** A real company: employees already use EMP-0001.. EMP-0004. */
    private function realEmployees(): User
    {
        $this->seedForced(RolePermissionSeeder::class);
        $owner = User::create(['name' => 'Real Owner', 'email' => 'owner@company.test', 'password' => 'secret-pass']);
        $owner->assignRole('Super Admin');
        foreach (range(1, 4) as $i) {
            Staff::create(['employee_code' => "EMP-000{$i}", 'first_name' => "Real{$i}", 'email' => "real{$i}@company.test", 'user_id' => $i === 1 ? $owner->id : null]);
        }

        return $owner;
    }

    public function test_on_a_live_server_db_seed_adds_only_reference_data(): void
    {
        $owner = $this->realEmployees();
        $this->onLiveServer();

        $this->seedForced(DatabaseSeeder::class);

        // No demo logins, clients or invoices.
        $this->assertSame(0, User::where('email', 'like', '%@example.com')->count());
        $this->assertSame(0, Client::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, PayrollRun::count());
        $this->assertSame(0, StaffSalaryStructure::count());

        // Reference data is there.
        $this->assertSame(4, Role::count());
        $this->assertGreaterThan(0, Tax::count());

        // Real data untouched.
        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('secret-pass', $owner->fresh()->password));
        $this->assertSame(4, Staff::count());
    }

    public function test_demo_data_can_be_forced_on_a_staging_server(): void
    {
        $this->onLiveServer();
        $this->setEnv('SEED_DEMO_DATA', 'true');

        $this->seedForced(DatabaseSeeder::class);

        $this->assertSame(4, User::where('email', 'like', '%@example.com')->count());
        $this->assertSame(6, Invoice::count());
    }

    public function test_demo_payroll_has_a_paid_month_and_a_draft_and_is_safe_to_run_again(): void
    {
        $this->seedForced(DatabaseSeeder::class);

        $runs = PayrollRun::orderBy('year')->orderBy('month')->get();
        $this->assertSame(['paid', 'draft'], $runs->pluck('status')->all());
        $this->assertSame([4, 4], $runs->pluck('employee_count')->all());
        $this->assertSame(2, PayrollAdjustment::count());

        // The demo employee: one absent day in both months, plus one unpaid leave day in the latest.
        $employee = Staff::where('email', 'employee@example.com')->sole();
        $this->assertSame(['1.0', '2.0'], $runs->map(fn ($r) => $r->payslips()->where('staff_id', $employee->id)->value('lop_days'))->all());
        $this->assertGreaterThan(0, (float) $runs[1]->payslips()->where('staff_id', $employee->id)->value('employee_esi'));

        $this->seedForced(PayrollDemoSeeder::class);
        $this->assertSame(2, PayrollRun::count());
        $this->assertSame(4, StaffSalaryStructure::count());
        $this->assertSame(2, PayrollAdjustment::count());
    }

    public function test_invoice_seeder_run_alone_on_live_adds_no_demo_clients(): void
    {
        $this->onLiveServer();

        $this->seedForced(InvoiceSeeder::class);

        $this->assertSame(0, Client::count());
        $this->assertGreaterThan(0, Tax::count()); // taxes and company settings are still fine
    }

    public function test_demo_users_get_free_staff_codes_when_real_employees_use_the_old_ones(): void
    {
        $this->realEmployees();

        $this->seedForced(DatabaseSeeder::class); // used to fail on the unique employee_code

        $demoCodes = Staff::whereIn('email', array_column(DemoUserSeeder::ACCOUNTS, 'email'))->pluck('employee_code');
        $this->assertCount(4, $demoCodes);
        $this->assertCount(8, Staff::pluck('employee_code')->unique());
        $this->assertNotContains('EMP-0001', $demoCodes->all());

        // Every demo login has its staff profile; a second run adds nothing.
        $this->seedForced(DatabaseSeeder::class);
        $this->assertSame(8, Staff::count());
        $this->assertSame(0, User::whereDoesntHave('staff')->where('email', 'like', '%@example.com')->count());
    }

    public function test_admin_seeder_refuses_the_default_password_on_live(): void
    {
        $this->onLiveServer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD');
        $this->seedForced(AdminUserSeeder::class);
    }

    public function test_admin_seeder_on_live_creates_admin_from_env_with_a_free_staff_code(): void
    {
        $this->realEmployees();
        $this->onLiveServer();
        $this->setEnv('ADMIN_EMAIL', 'boss@company.test');
        $this->setEnv('ADMIN_PASSWORD', 'Str0ng-Pass!');

        $this->seedForced(AdminUserSeeder::class);

        $admin = User::where('email', 'boss@company.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('Super Admin'));
        $this->assertTrue(Hash::check('Str0ng-Pass!', $admin->password));
        $this->assertSame('EMP-005', Staff::where('user_id', $admin->id)->value('employee_code'));
    }

    public function test_admin_seeder_keeps_an_existing_password_unless_one_is_given(): void
    {
        $owner = $this->realEmployees();
        $this->setEnv('ADMIN_EMAIL', 'owner@company.test');

        $this->seedForced(AdminUserSeeder::class);
        $this->assertTrue(Hash::check('secret-pass', $owner->fresh()->password));
        $this->assertSame('Real Owner', $owner->fresh()->name);

        $owner->createToken('app');
        $this->setEnv('ADMIN_PASSWORD', 'New-Pass-123');
        $this->seedForced(AdminUserSeeder::class);
        $this->assertTrue(Hash::check('New-Pass-123', $owner->fresh()->password));
        $this->assertSame(0, $owner->tokens()->count()); // signed out after the reset
    }
}
