<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venger_result_fish', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venger_result_id')->constrained('venger_results')->cascadeOnDelete();
            $table->string('fish_type', 40);
            $table->date('stocked_on');
            $table->unsignedInteger('stocked_pieces');
            $table->decimal('stocked_weight_kg', 14, 2);
            $table->date('sold_on')->nullable();
            $table->unsignedInteger('sold_pieces')->default(0);
            $table->decimal('sold_weight_kg', 14, 2)->default(0);
            $table->date('loss_on')->nullable();
            $table->unsignedInteger('loss_pieces')->default(0);
            $table->decimal('loss_weight_kg', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['venger_result_id', 'fish_type']);
        });

        $results = DB::table('venger_results')->orderBy('id')->get();
        foreach ($results as $row) {
            DB::table('venger_result_fish')->insert([
                'venger_result_id' => $row->id,
                'fish_type' => 'sazan',
                'stocked_on' => $row->stocked_on,
                'stocked_pieces' => $row->stocked_pieces,
                'stocked_weight_kg' => $row->stocked_weight_kg,
                'sold_on' => $row->sold_on,
                'sold_pieces' => $row->sold_pieces,
                'sold_weight_kg' => $row->sold_weight_kg,
                'loss_on' => null,
                'loss_pieces' => 0,
                'loss_weight_kg' => 0,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venger_result_fish');
    }
};
