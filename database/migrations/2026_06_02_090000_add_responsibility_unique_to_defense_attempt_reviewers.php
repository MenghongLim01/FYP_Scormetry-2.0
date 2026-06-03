<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A scoring responsibility is (defense session + user + scoring role). The
     * uniqueness rule must prevent exact duplicate responsibilities while still
     * allowing one reviewer to hold several different roles in the same session.
     */
    public function up(): void
    {
        // Collapse any pre-existing exact duplicates (same attempt + reviewer +
        // committee_role) before enforcing the constraint. Keep the oldest row and
        // repoint its reviews so no academic record is lost.
        $duplicates = DB::table('defense_attempt_reviewers')
            ->select('defense_attempt_id', 'reviewer_id', 'committee_role', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('defense_attempt_id', 'reviewer_id', 'committee_role')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $query = DB::table('defense_attempt_reviewers')
                ->where('defense_attempt_id', $group->defense_attempt_id)
                ->where('reviewer_id', $group->reviewer_id)
                ->where('id', '!=', $group->keep_id);

            // committee_role may be NULL; match accordingly.
            $group->committee_role === null
                ? $query->whereNull('committee_role')
                : $query->where('committee_role', $group->committee_role);

            $staleIds = $query->pluck('id');

            if ($staleIds->isNotEmpty()) {
                DB::table('reviews')
                    ->whereIn('defense_attempt_reviewer_id', $staleIds)
                    ->update(['defense_attempt_reviewer_id' => $group->keep_id]);

                DB::table('defense_attempt_reviewers')->whereIn('id', $staleIds)->delete();
            }
        }

        Schema::table('defense_attempt_reviewers', function (Blueprint $table) {
            $table->unique(
                ['defense_attempt_id', 'reviewer_id', 'committee_role'],
                'dar_attempt_reviewer_role_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('defense_attempt_reviewers', function (Blueprint $table) {
            $table->dropUnique('dar_attempt_reviewer_role_unique');
        });
    }
};
