<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('expenses', 'quantity')) {
                    $table->decimal('quantity', 14, 3)->nullable()->after('amount');
                }
                if (! Schema::hasColumn('expenses', 'unit_price')) {
                    $table->decimal('unit_price', 14, 2)->nullable()->after('quantity');
                }
            });

            DB::table('expenses')->where('category', 'transport')->update(['category' => 'main']);
            DB::table('expenses')->where('category', 'other')->update(['category' => 'emergency']);
        }

        if (! Schema::hasTable('farm_investments')) {
            Schema::create('farm_investments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('investor');
                $table->decimal('amount', 14, 2);
                $table->date('invested_at');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_investments');

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasColumn('expenses', 'unit_price')) {
                    $table->dropColumn('unit_price');
                }
                if (Schema::hasColumn('expenses', 'quantity')) {
                    $table->dropColumn('quantity');
                }
            });
        }
    }
};
