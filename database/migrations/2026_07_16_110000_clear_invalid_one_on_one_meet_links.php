<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Earlier builds auto-generated random meet.google.com codes that Google does not recognize.
     * Clear them so organizers can paste real links from meet.google.com/new or Calendar.
     */
    public function up(): void
    {
        DB::table('one_on_one_meetings')
            ->whereNotNull('google_meet_link')
            ->where('google_meet_link', 'like', 'https://meet.google.com/%')
            ->update(['google_meet_link' => null]);
    }

    public function down(): void
    {
        // Cannot restore cleared links.
    }
};
