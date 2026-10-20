<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venger_clients', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('region')->nullable()->after('address');
            $table->string('district')->nullable()->after('region');
        });

        foreach (DB::table('venger_clients')->orderBy('id')->get() as $row) {
            $parts = preg_split('/\s+/u', trim((string) $row->name), 2) ?: [];
            DB::table('venger_clients')->where('id', $row->id)->update([
                'first_name' => $parts[0] ?? $row->name,
                'last_name' => $parts[1] ?? '',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('venger_clients', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'region', 'district']);
        });
    }
};
