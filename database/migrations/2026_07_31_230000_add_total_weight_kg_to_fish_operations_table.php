<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_operations', function (Blueprint $table) {
            $table->decimal('total_weight_kg', 12, 3)->nullable()->after('average_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('fish_operations', function (Blueprint $table) {
            $table->dropColumn('total_weight_kg');
        });
    }
};
