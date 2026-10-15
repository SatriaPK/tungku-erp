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
        Schema::create('track_stage_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_stage_progress_id')
                ->constrained('track_stage_progress')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('result_url');
            $table->text('review_notes')->nullable();
            $table->foreignId('submitted_by')->constrained('users');
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique(
                ['track_stage_progress_id', 'version'],
                'submission_progress_version_unique',
            );
            $table->index('track_stage_progress_id', 'submission_progress_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_stage_submissions');
    }
};
