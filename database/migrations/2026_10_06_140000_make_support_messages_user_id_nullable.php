<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_messages')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Fresh installs already create user_id as nullable.
        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            $column = collect(DB::select('SHOW COLUMNS FROM support_messages WHERE Field = ?', ['user_id']))->first();
            if ($column && strtoupper((string) $column->Null) === 'YES') {
                return;
            }

            Schema::table('support_messages', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            DB::statement('ALTER TABLE support_messages MODIFY user_id BIGINT UNSIGNED NULL');

            Schema::table('support_messages', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        //
    }
};
