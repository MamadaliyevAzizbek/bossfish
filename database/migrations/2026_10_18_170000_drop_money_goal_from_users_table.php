<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'money_goal')) {
                $table->dropColumn(['money_goal', 'money_saved']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('money_goal')->default(0)->after('access_expires_at');
            $table->unsignedBigInteger('money_saved')->default(0)->after('money_goal');
        });
    }
};
