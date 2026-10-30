<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 160);
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel', 16);
            $table->string('notification_type');
            $table->uuid('notification_id')->unique();
            $table->json('payload');
            $table->json('mail_data');
            $table->timestamp('occurred_at');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('lease_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['event_key', 'recipient_id', 'channel'], 'notification_delivery_identity');
            $table->index(['status', 'available_at']);
            $table->index(['status', 'lease_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
