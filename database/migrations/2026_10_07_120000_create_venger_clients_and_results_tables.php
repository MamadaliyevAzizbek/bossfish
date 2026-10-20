<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venger_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 32);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('venger_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venger_client_id')->constrained('venger_clients')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('area_ha', 12, 3);
            $table->date('stocked_on');
            $table->unsignedInteger('stocked_pieces');
            $table->decimal('stocked_weight_kg', 14, 2);
            $table->decimal('feed_kg', 14, 2);
            $table->date('sold_on');
            $table->unsignedInteger('sold_pieces');
            $table->decimal('sold_weight_kg', 14, 2);
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venger_results');
        Schema::dropIfExists('venger_clients');
    }
};
