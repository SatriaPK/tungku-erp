<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MODIFY is MySQL/MariaDB-only; SQLite (used by tests) has no TEXT length limit.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE packages MODIFY description TEXT NULL');
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE packages MODIFY description TINYTEXT NULL');
        }
    }
};
