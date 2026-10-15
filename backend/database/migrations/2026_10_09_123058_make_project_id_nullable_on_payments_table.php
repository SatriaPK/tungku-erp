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
        Schema::table('payments', function (Blueprint $table) {
            // Drop existing foreign key constraint first
            $table->dropForeign(['project_id']);

            // Make column nullable
            $table->unsignedBigInteger('project_id')->nullable()->change();

            // Re-add foreign key with null on delete
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop nullable FK
            $table->dropForeign(['project_id']);

            // Make NOT NULL (only safe if no null values exist)
            $table->unsignedBigInteger('project_id')->nullable(false)->change();

            // Re-add strict FK
            $table->foreign('project_id')
                ->references('id')
                ->on('projects');
        });
    }
};
