<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('employee_code')->nullable()->after('user_id')->unique();
            $table->string('first_name')->nullable()->after('employee_code');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('department_id')->nullable()->after('phone')->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('designation_id')->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('team_id')->constrained('staff')->nullOnDelete();
            $table->date('joining_date')->nullable()->after('manager_id');
            $table->string('employment_status')->default('active')->after('joining_date');
            $table->string('name')->nullable()->change();
            $table->string('position')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn([
                'employee_code', 'first_name', 'last_name', 'email', 'phone',
                'joining_date', 'employment_status',
            ]);
            $table->string('name')->nullable(false)->change();
            $table->string('position')->nullable(false)->change();
        });
    }
};
