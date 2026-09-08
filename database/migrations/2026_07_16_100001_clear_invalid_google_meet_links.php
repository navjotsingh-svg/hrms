<?php

use App\Models\OneOnOneMeeting;
use App\Services\GoogleMeetService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(GoogleMeetService::class);

        OneOnOneMeeting::query()
            ->whereNotNull('google_meet_link')
            ->orderBy('id')
            ->chunkById(100, function ($meetings) use ($service) {
                foreach ($meetings as $meeting) {
                    if (! $service->isValidMeetLink($meeting->google_meet_link)) {
                        $meeting->update(['google_meet_link' => null]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Invalid links were cleared; no automatic restore.
    }
};
