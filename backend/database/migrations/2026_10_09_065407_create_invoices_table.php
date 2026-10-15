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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique(); // INV-YYYY-XXXX
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recording_session_id')->nullable()->constrained()->nullOnDelete();

            // draft | issued | paid | cancelled | refunded
            $table->string('status', 20)->default('draft');

            // Amounts in IDR cents
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            // Notes and metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Additional flexible data

            // Date tracking
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['client_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['recording_session_id']);
            $table->index(['status', 'due_date']);
            $table->index('issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
