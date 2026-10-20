<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['farm_id', 'name']);
        });

        if (! Schema::hasTable('expenses')) {
            return;
        }

        DB::table('expenses')->whereIn('category', ['main', 'emergency', 'equipment'])->update(['category' => 'other']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE expenses MODIFY COLUMN category VARCHAR(64) NOT NULL DEFAULT \'other\'');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses')) {
            DB::table('expenses')->where('category', 'other')->update(['category' => 'main']);

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement(
                    "ALTER TABLE expenses MODIFY COLUMN category "
                    ."ENUM('main','medicine','equipment','salary','feed','emergency') NOT NULL DEFAULT 'emergency'"
                );
            }
        }

        Schema::dropIfExists('finance_sections');
    }
};
