<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('defense_attempt_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defense_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('committee_role')->nullable();
            $table->string('status')->default('active');
            $table->boolean('excluded_from_calculation')->default(false);
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['defense_attempt_id', 'status']);
            $table->index(['reviewer_id', 'status']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('defense_attempt_reviewer_id')->nullable()->after('defense_attempt_id')
                ->constrained('defense_attempt_reviewers')
                ->nullOnDelete();
        });

        foreach (DB::table('defense_attempts')->orderBy('id')->get(['id', 'team_id']) as $attempt) {
            $team = DB::table('teams')->where('id', $attempt->team_id)->first(['subject_id']);

            if (! $team) {
                continue;
            }

            $reviewers = DB::table('team_members')
                ->join('subject_members', function ($join) use ($team) {
                    $join->on('subject_members.user_id', '=', 'team_members.user_id')
                        ->where('subject_members.subject_id', '=', $team->subject_id)
                        ->where('subject_members.role', '!=', 'student')
                        ->where('subject_members.status', '=', 'approved');
                })
                ->where('team_members.team_id', $attempt->team_id)
                ->get([
                    'team_members.user_id',
                    'subject_members.role',
                    'subject_members.role_label',
                ]);

            foreach ($reviewers as $reviewer) {
                $assignmentId = DB::table('defense_attempt_reviewers')->insertGetId([
                    'defense_attempt_id' => $attempt->id,
                    'reviewer_id' => $reviewer->user_id,
                    'committee_role' => $reviewer->role_label ?: $reviewer->role,
                    'status' => 'active',
                    'excluded_from_calculation' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('reviews')
                    ->where('defense_attempt_id', $attempt->id)
                    ->where('reviewer_id', $reviewer->user_id)
                    ->whereNull('defense_attempt_reviewer_id')
                    ->update(['defense_attempt_reviewer_id' => $assignmentId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defense_attempt_reviewer_id');
        });

        Schema::dropIfExists('defense_attempt_reviewers');
    }
};
