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
        Schema::table('crm_campaign_recipients', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->unique();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_delivery_event_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_campaign_recipients', function (Blueprint $table) {
            $table->dropUnique(['provider_message_id']);
            $table->dropColumn(['provider_message_id', 'delivered_at', 'last_delivery_event_at']);
        });
    }
};
