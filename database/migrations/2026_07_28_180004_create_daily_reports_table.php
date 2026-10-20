<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('water_temp', 5, 2)->nullable();
            $table->decimal('ph_level', 4, 2)->nullable();
            $table->decimal('feeding_kg', 10, 2)->nullable();
            $table->unsignedInteger('mortality_count')->default(0);
            $table->string('fish_condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['pond_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
