<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_rations', function (Blueprint $table) {
            $table->decimal('sale_price_per_kg', 14, 2)->nullable()->after('kop_narx');
        });
    }

    public function down(): void
    {
        Schema::table('feed_rations', function (Blueprint $table) {
            $table->dropColumn('sale_price_per_kg');
        });
    }
};
