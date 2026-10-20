<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'pond_id')) {
                $table->foreignId('pond_id')
                    ->nullable()
                    ->after('farm_id')
                    ->constrained()
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'fish_operation_id')) {
                $table->foreignId('fish_operation_id')
                    ->nullable()
                    ->after('warehouse_item_id')
                    ->constrained('fish_operations')
                    ->nullOnDelete();
                $table->unique('fish_operation_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'fish_operation_id')) {
                $table->dropUnique(['fish_operation_id']);
                $table->dropConstrainedForeignId('fish_operation_id');
            }
            if (Schema::hasColumn('expenses', 'pond_id')) {
                $table->dropConstrainedForeignId('pond_id');
            }
        });
    }
};
