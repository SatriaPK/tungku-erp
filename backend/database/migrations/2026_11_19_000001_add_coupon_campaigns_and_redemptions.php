<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            // Existing single-use codes retain their previous semantics.
            $table->string('kind', 16)->default('legacy');
            $table->uuid('parent_id')->nullable()->index();
            $table->foreign('parent_id')->references('id')->on('coupons')->restrictOnDelete();
            $table->string('package_mode', 16)->default('all');
            $table->json('package_ids')->nullable();
            $table->boolean('minimum_enabled')->default(true);
            $table->boolean('maximum_enabled')->default(true);
            $table->boolean('validity_enabled')->default(true);
            $table->boolean('quota_enabled')->default(true);
        });
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('coupon_id');
            $table->foreign('coupon_id')->references('id')->on('coupons')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('invoice_id')->index()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->string('status', 16)->default('reserved');
            $table->unsignedBigInteger('discount_amount');
            $table->json('rule_snapshot');
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['coupon_id', 'status']);
            $table->index(['coupon_id', 'client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['kind', 'parent_id', 'package_mode', 'package_ids', 'minimum_enabled', 'maximum_enabled', 'validity_enabled', 'quota_enabled']);
        });
    }
};
