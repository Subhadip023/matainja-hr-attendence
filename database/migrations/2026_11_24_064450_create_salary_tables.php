<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll setup: the salary components you pay (Basic, HRA, ...), each
 * employee's statutory & bank details, and their salary revisions.
 * A revision keeps its own copy of each component's name and amount, so
 * editing a component later never changes a salary already agreed.
 * PF, ESI, Professional Tax and TDS are worked out by the payroll run from
 * the profile flags below; they are not stored as fixed amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();          // BASIC, HRA, SPECIAL, ...
            $table->string('type', 20)->default('earning'); // earning, deduction
            $table->string('calculation', 30)->default('fixed'); // fixed, percent_of_gross, percent_of_basic, balance
            $table->decimal('value', 12, 2)->default(0);   // amount (fixed) or percent
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_prorated')->default(true); // cut for loss-of-pay days
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->unique()->constrained('staff')->cascadeOnDelete();
            $table->string('pan', 10)->nullable();
            $table->string('uan', 12)->nullable();
            $table->string('pf_number', 30)->nullable();
            $table->string('esi_number', 17)->nullable();
            $table->boolean('pf_applicable')->default(true);
            $table->boolean('pf_on_full_basic')->default(false); // false = PF wage capped at the statutory ceiling
            $table->boolean('esi_applicable')->default(true);    // still only deducted while gross is under the ESI limit
            $table->boolean('pt_applicable')->default(true);
            $table->string('tax_regime', 10)->default('new');   // new, old
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->text('bank_account_number')->nullable();    // encrypted
            $table->string('bank_ifsc', 11)->nullable();
            $table->timestamps();
        });

        Schema::create('staff_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('monthly_gross', 12, 2);  // sum of earnings
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'effective_from']);
        });

        Schema::create('staff_salary_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 30);
            $table->string('type', 20);
            $table->decimal('monthly_amount', 12, 2);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_prorated')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_salary_structure_items');
        Schema::dropIfExists('staff_salary_structures');
        Schema::dropIfExists('staff_payroll_profiles');
        Schema::dropIfExists('salary_components');
    }
};
