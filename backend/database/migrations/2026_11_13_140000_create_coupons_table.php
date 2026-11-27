<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 24)->unique();
            $table->string('name', 80);
            $table->string('type');
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('minimum')->default(0);
            $table->unsignedBigInteger('maximum')->default(0);
            $table->date('start');
            $table->date('end');
            $table->unsignedInteger('limit');
            $table->unsignedInteger('used')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
