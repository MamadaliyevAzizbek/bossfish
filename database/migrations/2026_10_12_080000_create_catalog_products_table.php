<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // feed | medicine | equipment
            $table->string('name');
            $table->string('slogan')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->string('price_unit')->nullable(); // masalan: so'm / qop
            $table->string('usage_text')->nullable();
            $table->string('theme', 32)->default('teal');
            $table->json('specs')->nullable();
            $table->json('features')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });

        (new \Database\Seeders\CatalogProductSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
    }
};
