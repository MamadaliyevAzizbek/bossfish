<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pond_create_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('name');
            $table->string('region');
            $table->string('district');
            $table->decimal('area_m2', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('pond_id')->nullable()->constrained('ponds')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'farm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pond_create_requests');
    }
};
