<?php

use App\Http\Controllers\Admin;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'));

/*
| Super Admin web panel (/admin). Session auth on the "web" guard; the
| mobile app keeps using the Sanctum token API under /api/v1.
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.attempt');
    });

    Route::middleware(['auth', 'super.admin'])->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');

        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Employees
        Route::get('/employees', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::get('/employees/create', [Admin\StaffController::class, 'create'])->name('staff.create');
        Route::post('/employees', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::get('/employees/{staff}/edit', [Admin\StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/employees/{staff}', [Admin\StaffController::class, 'update'])->name('staff.update');
        Route::post('/employees/{staff}/deactivate', [Admin\StaffController::class, 'deactivate'])->name('staff.deactivate');
        Route::post('/employees/{staff}/reactivate', [Admin\StaffController::class, 'reactivate'])->name('staff.reactivate');
        Route::delete('/employees/{staff}', [Admin\StaffController::class, 'destroy'])->name('staff.destroy');

        // Users & roles
        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::put('/users/{user}/roles', [Admin\UserController::class, 'updateRoles'])->name('users.roles');
        Route::post('/users/{user}/password', [Admin\UserController::class, 'resetPassword'])->name('users.password');
        Route::post('/users/{user}/sign-out', [Admin\UserController::class, 'revokeTokens'])->name('users.revoke');
        Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [Admin\RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');

        // Organisation setup (branches, departments, designations, teams, shifts, holidays, leave types)
        Route::get('/organisation/{type}', [Admin\OrgController::class, 'index'])->name('org.index');
        Route::post('/organisation/{type}', [Admin\OrgController::class, 'store'])->name('org.store');
        Route::get('/organisation/{type}/{id}/edit', [Admin\OrgController::class, 'edit'])->name('org.edit');
        Route::put('/organisation/{type}/{id}', [Admin\OrgController::class, 'update'])->name('org.update');
        Route::delete('/organisation/{type}/{id}', [Admin\OrgController::class, 'destroy'])->name('org.destroy');

        // Invoices
        Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [Admin\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [Admin\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf', [Admin\InvoiceController::class, 'pdf'])->name('invoices.pdf');
        // Old browser-print link, kept so bookmarks still work.
        Route::get('/invoices/{invoice}/print', fn (Invoice $invoice) => redirect()->route('admin.invoices.pdf', ['invoice' => $invoice, 'inline' => 1]));
        Route::get('/invoices/{invoice}/edit', [Admin\InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [Admin\InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('/invoices/{invoice}/status', [Admin\InvoiceController::class, 'status'])->name('invoices.status');
        Route::delete('/invoices/{invoice}', [Admin\InvoiceController::class, 'destroy'])->name('invoices.destroy');

        // Attendance & approvals
        Route::get('/attendance', [Admin\AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/export', [Admin\AttendanceController::class, 'export'])->name('attendance.export');
        Route::get('/approvals', [Admin\ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{kind}/{id}/{decision}', [Admin\ApprovalController::class, 'decide'])
            ->whereIn('kind', ['leave', 'wfh', 'correction'])
            ->whereIn('decision', ['approve', 'reject'])
            ->name('approvals.decide');

        // Settings & audit log
        Route::get('/settings', [Admin\SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::get('/audit-log', [Admin\AuditLogController::class, 'index'])->name('audit.index');
    });
});
