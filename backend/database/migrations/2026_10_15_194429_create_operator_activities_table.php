<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operator_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained('track_stage_submissions')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('operator_name')->nullable();
            $table->string('project_name')->nullable();
            $table->string('track_title')->nullable();
            $table->string('stage', 20);
            $table->dateTime('deadline_at')->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('recorded_at');
            $table->unique(['type', 'source_id']);
            $table->index(['occurred_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_activities');
    }
};
