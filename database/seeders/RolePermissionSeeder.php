<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed roles and their permissions per the API specification.
     */
    public function run(): void
    {
        $permissions = [
            'employees.view_all', 'employees.create', 'employees.update', 'employees.deactivate',
            'attendance.view_all', 'attendance.view_team', 'attendance.punch', 'attendance.correct',
            'leave.approve', 'leave.apply', 'reports.view', 'reports.view_all',
            'roles.manage', 'settings.manage', 'audit.view',
            'organisation.manage', 'announcements.manage', 'documents.manage',
            'expenses.approve', 'expenses.mark_paid', 'assets.manage', 'tickets.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $all = Permission::pluck('id', 'name');

        $matrix = [
            'Super Admin' => $all->values()->all(),
            'HR Admin' => $all->only([
                'employees.view_all', 'employees.create', 'employees.update', 'employees.deactivate',
                'attendance.view_all', 'attendance.correct', 'leave.approve', 'leave.apply',
                'reports.view', 'reports.view_all', 'organisation.manage', 'announcements.manage',
                'documents.manage', 'expenses.approve', 'expenses.mark_paid',
                'assets.manage', 'tickets.manage',
            ])->values()->all(),
            'Manager' => $all->only([
                'employees.create',
                'attendance.view_team', 'leave.approve', 'leave.apply',
                'reports.view', 'expenses.approve',
            ])->values()->all(),
            'Employee' => $all->only(['attendance.punch', 'leave.apply'])->values()->all(),
        ];

        foreach ($matrix as $roleName => $permissionIds) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->permissions()->sync($permissionIds);
        }
    }
}
