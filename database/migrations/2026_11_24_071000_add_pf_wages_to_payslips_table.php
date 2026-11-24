<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wages PF and pension were worked out on, as the EPFO ECR file needs
 * them (EPF wages, and EPS / EDLI wages capped at the wage ceiling).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('pf_wages', 12, 2)->default(0)->after('employee_pf');
            $table->decimal('eps_wages', 12, 2)->default(0)->after('pf_wages');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['pf_wages', 'eps_wages']);
        });
    }
};
