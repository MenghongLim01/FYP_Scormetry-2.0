<?php

use App\Models\DefenseAttempt;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill DefenseAttemptReviewer rows for the subject owner on every
     * existing defense attempt. New attempts handle this via the model `booted`
     * hook on DefenseAttempt — this migration takes care of historical rows.
     */
    public function up(): void
    {
        DefenseAttempt::with('team.subject')
            ->chunkById(200, function ($attempts) {
                foreach ($attempts as $attempt) {
                    $attempt->ensureOwnerIsReviewer();
                }
            });
    }

    /**
     * No-op down — the assignments may have been promoted to active reviewers
     * with submitted reviews; rolling back could destroy real data.
     */
    public function down(): void
    {
        //
    }
};
