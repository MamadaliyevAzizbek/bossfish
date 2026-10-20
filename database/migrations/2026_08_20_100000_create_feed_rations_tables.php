<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_per_kg', 14, 2);
            $table->string('category', 20)->default('feed');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('feed_rations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('kop_narx', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('feed_ration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_ration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_per_100kg', 10, 3);
            $table->timestamps();

            $table->unique(['feed_ration_id', 'feed_product_id']);
        });

        (new \Database\Seeders\FeedProductSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_ration_items');
        Schema::dropIfExists('feed_rations');
        Schema::dropIfExists('feed_products');
    }
};
