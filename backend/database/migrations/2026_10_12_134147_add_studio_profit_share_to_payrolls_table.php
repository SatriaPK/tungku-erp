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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('studio_profit_percentage', 5, 2)->default(50)->after('net_profit');
            $table->unsignedBigInteger('studio_profit_amount')->default(0)->after('studio_profit_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['studio_profit_percentage', 'studio_profit_amount']);
        });
    }
};
