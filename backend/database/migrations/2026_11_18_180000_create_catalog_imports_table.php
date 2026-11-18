<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_imports', function (Blueprint $table): void {
            $table->string('source', 100)->primary();
            $table->string('checksum', 64);
            $table->timestamp('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_imports');
    }
};
