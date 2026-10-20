<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fish_operations', function (Blueprint $table) {
            $table->string('fish_type')->nullable()->after('type');
            $table->decimal('average_weight_kg', 10, 3)->nullable()->after('quantity');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('details')->nullable();
            $table->dateTime('performed_at');
            $table->timestamps();

            $table->index(['pond_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        Schema::table('fish_operations', function (Blueprint $table) {
            $table->dropColumn(['fish_type', 'average_weight_kg']);
        });
    }
};
