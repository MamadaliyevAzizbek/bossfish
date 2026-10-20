<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ponds', function (Blueprint $table) {
            $table->unsignedInteger('current_stock')->default(0)->after('area_m2');
        });

        Schema::create('fish_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['add', 'sale', 'loss']); // qo'shish, sotish, poterya
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 14, 2)->nullable(); // sotuv summasi ixtiyoriy
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->date('operated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fish_operations');
        Schema::table('ponds', function (Blueprint $table) {
            $table->dropColumn('current_stock');
        });
    }
};
