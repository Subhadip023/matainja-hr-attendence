<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly payroll runs and the payslips they produce. A payslip keeps its
 * own copy of the employee's details and every amount, so later changes to
 * the employee, their salary or the statutory rates never alter it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft'); // draft, locked, paid
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_employer_contributions', 14, 2)->default(0);
            $table->json('skipped')->nullable(); // employees left out, with the reason
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('staff_salary_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 50)->nullable();
            $table->string('employee_name');
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('uan', 12)->nullable();
            $table->string('tax_regime', 10)->default('new');
            $table->unsignedTinyInteger('period_days');            // days the monthly salary is divided by
            $table->decimal('lop_days_calculated', 4, 1)->default(0);
            $table->decimal('lop_days_override', 4, 1)->nullable(); // set by an admin on review
            $table->decimal('lop_days', 4, 1)->default(0);
            $table->decimal('paid_days', 4, 1)->default(0);
            $table->json('day_counts')->nullable();                 // present, paid leave, holidays, absent, ...
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('gross_earnings', 12, 2)->default(0);
            $table->decimal('taxable_earnings', 12, 2)->default(0);
            $table->decimal('employee_pf', 12, 2)->default(0);
            $table->decimal('employee_esi', 12, 2)->default(0);
            $table->decimal('professional_tax', 12, 2)->default(0);
            $table->decimal('tds', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->decimal('employer_pf', 12, 2)->default(0);
            $table->decimal('employer_eps', 12, 2)->default(0);
            $table->decimal('employer_esi', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'staff_id']);
            $table->index('staff_id');
        });

        Schema::create('payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('type', 20);     // earning, deduction
            $table->string('source', 20);   // salary, statutory, adjustment, overtime
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('type', 20);      // earning, deduction
            $table->string('name', 100);     // e.g. Diwali bonus, Salary advance recovery
            $table->decimal('amount', 12, 2);
            $table->boolean('is_taxable')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['payroll_run_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payslip_items');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
