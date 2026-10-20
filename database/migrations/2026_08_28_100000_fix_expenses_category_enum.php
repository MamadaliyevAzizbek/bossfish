<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('expenses')->where('category', 'transport')->update(['category' => 'main']);
        DB::table('expenses')->where('category', 'other')->update(['category' => 'emergency']);

        DB::statement(
            "ALTER TABLE expenses MODIFY COLUMN category "
            ."ENUM('main','medicine','equipment','salary','feed','emergency') NOT NULL DEFAULT 'emergency'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('expenses')->where('category', 'main')->update(['category' => 'transport']);
        DB::table('expenses')->where('category', 'emergency')->update(['category' => 'other']);

        DB::statement(
            "ALTER TABLE expenses MODIFY COLUMN category "
            ."ENUM('feed','medicine','salary','equipment','transport','other') NOT NULL DEFAULT 'other'"
        );
    }
};
