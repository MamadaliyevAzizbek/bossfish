<?php

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('director_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });

        User::query()
            ->where('role', User::ROLE_WORKER)
            ->whereNull('director_id')
            ->each(function (User $worker) {
                $farm = $worker->assignedFarms()->first();
                if ($farm?->director_id) {
                    $worker->update(['director_id' => $farm->director_id]);
                    return;
                }

                $pond = $worker->assignedPonds()->with('farm')->first();
                $directorId = $pond?->director_id ?? $pond?->farm?->director_id;
                if ($directorId) {
                    $worker->update(['director_id' => $directorId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('director_id');
        });
    }
};
