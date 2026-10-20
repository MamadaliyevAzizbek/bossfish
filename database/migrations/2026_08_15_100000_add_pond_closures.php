<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ponds', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('status');
        });

        Schema::create('pond_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20);
            $table->date('closed_on');
            $table->json('estimated_stock');
            $table->json('actual_stock')->nullable();
            $table->json('adjustments')->nullable();
            $table->json('summary_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pond_closures');

        Schema::table('ponds', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
