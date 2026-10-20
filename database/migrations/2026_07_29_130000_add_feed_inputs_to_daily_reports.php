<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->string('feed_name')->nullable()->after('report_date');
            $table->decimal('wheat_bran_kg', 10, 2)->nullable()->after('feeding_kg');
            $table->enum('fertilizer_type', ['amafos', 'slitra'])->nullable()->after('wheat_bran_kg');
            $table->decimal('fertilizer_kg', 10, 2)->nullable()->after('fertilizer_type');
        });

        Schema::create('feed_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['pond_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_types');

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn([
                'feed_name',
                'wheat_bran_kg',
                'fertilizer_type',
                'fertilizer_kg',
            ]);
        });
    }
};
