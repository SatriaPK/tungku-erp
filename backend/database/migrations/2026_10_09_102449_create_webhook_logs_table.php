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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50); // midtrans, xendit, manual
            $table->string('event_type', 100)->nullable(); // transaction.paid, invoice.expired, etc.
            $table->string('transaction_id')->nullable()->index(); // Gateway's transaction ID
            $table->string('order_id')->nullable()->index(); // Our order_id
            $table->json('payload'); // Full webhook payload
            $table->json('headers')->nullable(); // Request headers for signature verification
            $table->string('status', 50)->default('received'); // received, processed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Unique constraint for idempotency (gateway + transaction_id combination)
            $table->unique(['gateway', 'transaction_id'], 'webhook_idempotency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
