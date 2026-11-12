<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->json('facilities')->nullable();
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organiser_id')->constrained('staff')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('room_id')->nullable()->constrained('meeting_rooms')->nullOnDelete();
            $table->string('meeting_url')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, cancelled
            $table->timestamps();
            $table->index(['starts_at', 'status']);
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('response_status')->default('invited'); // invited, accepted, declined, tentative
            $table->timestamps();
            $table->unique(['meeting_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('meeting_rooms');
    }
};
