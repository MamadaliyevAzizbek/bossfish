<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('director_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('plan', 32); // cabinet | control | individual
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->date('next_check_at')->nullable();
            $table->unsignedSmallInteger('check_interval_days')->default(7);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('status', 24)->default('active'); // active | attention | paused | expired
            $table->string('attention_note')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'next_check_at']);
            $table->index('plan');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_subscriptions');
    }
};
