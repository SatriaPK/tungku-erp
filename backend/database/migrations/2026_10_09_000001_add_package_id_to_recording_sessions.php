<?php

use App\Models\Package;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recording_sessions', function (Blueprint $table) {
            // Nullable: a hold can be created before a package is chosen,
            // but the API requires it. Existing rows and extension sessions
            // created internally may omit it.
            $table->foreignId('package_id')
                ->nullable()
                ->after('client_id')
                ->constrained('packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recording_sessions', function (Blueprint $table) {
            $table->dropForeignIdFor(Package::class);
            $table->dropColumn('package_id');
        });
    }
};
