<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fish_operations')->where('fish_type', 'talstalob')->update([
            'fish_type' => 'tolstolob',
        ]);

        DB::table('fish_operations')->where('fish_type', 'amur')->update([
            'fish_type' => 'oq_amur',
        ]);

        DB::table('fish_operations')->whereIn('fish_type', ['chorni', 'pyostri'])->update([
            'fish_type' => 'targil_tolstolob',
        ]);

        $logs = DB::table('activity_logs')
            ->whereIn('details->fish_type', ['talstalob', 'amur', 'chorni', 'pyostri'])
            ->get(['id', 'details']);

        foreach ($logs as $log) {
            $details = json_decode($log->details, true) ?? [];
            $details['fish_type'] = match ($details['fish_type'] ?? null) {
                'talstalob' => 'tolstolob',
                'amur' => 'oq_amur',
                'chorni', 'pyostri' => 'targil_tolstolob',
                default => $details['fish_type'] ?? null,
            };

            DB::table('activity_logs')->where('id', $log->id)->update([
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        // Standart nomlarni eski imlo xatolariga qaytarmaymiz.
    }
};
