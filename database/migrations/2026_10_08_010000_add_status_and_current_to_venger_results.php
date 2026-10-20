<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venger_results', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('notes');
        });

        Schema::table('venger_result_fish', function (Blueprint $table) {
            $table->date('current_on')->nullable()->after('stocked_weight_kg');
            $table->unsignedInteger('current_pieces')->nullable()->after('current_on');
            $table->decimal('current_weight_kg', 14, 2)->nullable()->after('current_pieces');
        });
    }

    public function down(): void
    {
        Schema::table('venger_result_fish', function (Blueprint $table) {
            $table->dropColumn(['current_on', 'current_pieces', 'current_weight_kg']);
        });

        Schema::table('venger_results', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
