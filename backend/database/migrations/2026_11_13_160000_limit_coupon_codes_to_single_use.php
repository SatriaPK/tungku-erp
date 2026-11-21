<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void { DB::table('coupons')->update(['limit' => 1]); }
    public function down(): void { /* Historical usage limits cannot be reconstructed. */ }
};
