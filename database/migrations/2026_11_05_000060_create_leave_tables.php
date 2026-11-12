<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('paid')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening', 5, 1)->default(0);
            $table->decimal('accrued', 5, 1)->default(0);
            $table->decimal('used', 5, 1)->default(0);
            $table->decimal('pending', 5, 1)->default(0);
            $table->timestamps();
            $table->unique(['staff_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('partial_day')->default(false);
            $table->decimal('total_days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, withdrawn, cancelled
            $table->timestamps();
            $table->index(['staff_id', 'status']);
        });

        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision')->nullable(); // approved, rejected, null=pending
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedSmallInteger('step_order')->default(1);
            $table->timestamps();
            $table->index(['approver_id', 'decision']);
        });

        Schema::create('wfh_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approver_comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_requests');
        Schema::dropIfExists('leave_approvals');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
