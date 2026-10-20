<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('main_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->time('test_time')->nullable();
            $table->decimal('do_mg_l', 8, 3)->nullable();
            $table->decimal('ph_level', 4, 2)->nullable();
            $table->decimal('ammonia_mg_l', 8, 3)->nullable();
            $table->decimal('nitrite_mg_l', 8, 3)->nullable();
            $table->decimal('water_transparency_cm', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['pond_id', 'report_date']);
        });

        Schema::create('main_report_fish_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_report_id')->constrained()->cascadeOnDelete();
            $table->string('fish_type');
            $table->decimal('average_weight_kg', 10, 3);
            $table->timestamps();
            $table->unique(['main_report_id', 'fish_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('main_report_fish_weights');
        Schema::dropIfExists('main_reports');
    }
};
