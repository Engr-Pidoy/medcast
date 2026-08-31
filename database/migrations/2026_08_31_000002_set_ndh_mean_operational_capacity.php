<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hospitalId = DB::table('hospitals')->where('code', 'NDH')->value('id');
        if ($hospitalId === null) {
            return;
        }

        DB::table('hospitals')->where('id', $hospitalId)->update(['total_beds' => 120]);
        DB::table('daily_admissions')
            ->where('hospital_id', $hospitalId)
            ->whereNotNull('occupied_beds')
            ->update(['occupancy_rate' => DB::raw('ROUND((occupied_beds * 100.0) / 120, 2)')]);
    }

    public function down(): void
    {
        $hospitalId = DB::table('hospitals')->where('code', 'NDH')->value('id');
        if ($hospitalId === null) {
            return;
        }

        DB::table('hospitals')->where('id', $hospitalId)->update(['total_beds' => 100]);
        DB::table('daily_admissions')
            ->where('hospital_id', $hospitalId)
            ->whereNotNull('occupied_beds')
            ->update(['occupancy_rate' => DB::raw('ROUND((occupied_beds * 100.0) / 100, 2)')]);
    }
};
