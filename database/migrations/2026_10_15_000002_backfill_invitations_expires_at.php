<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gives invitations that predate expiry the 7 days their already-sent signed links carry,
     * counted from creation. Only rows that are still null are touched, so running this again
     * changes nothing, and updated_at is left alone.
     */
    public function up(): void
    {
        DB::table('invitations')
            ->whereNull('expires_at')
            ->chunkById(500, function (Collection $invitations): void {
                foreach ($invitations as $invitation) {
                    $createdAt = $invitation->created_at ? Carbon::parse($invitation->created_at) : now();

                    DB::table('invitations')
                        ->where('id', $invitation->id)
                        ->update(['expires_at' => $createdAt->addDays(7)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally empty: rolling back the schema migration drops the column.
     */
    public function down(): void
    {
        //
    }
};
