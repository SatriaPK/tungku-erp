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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number')->default(1);

            // Item description
            $table->string('description');
            $table->text('details')->nullable();

            // Pricing
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('subtotal'); // unit_price * quantity
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total'); // subtotal + tax - discount

            // Optional references for specific item types
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recording_session_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['invoice_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
