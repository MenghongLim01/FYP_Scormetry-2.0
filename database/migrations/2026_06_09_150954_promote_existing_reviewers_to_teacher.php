<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Anyone who is a reviewer (a non-student subject member, any status) should
     * have a teacher-level account. This retroactively promotes existing reviewer
     * accounts that were created as 'student', so they get the reviewer experience
     * without a manual role change in the admin panel. Admins are never touched.
     */
    public function up(): void
    {
        $reviewerUserIds = DB::table('subject_members')
            ->where('role', '!=', 'student')
            ->distinct()
            ->pluck('user_id');

        if ($reviewerUserIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $reviewerUserIds)
            ->where('role', 'student')
            ->update(['role' => 'teacher']);
    }

    public function down(): void
    {
        // Not reversible — we can't know which teachers were previously students.
    }
};
