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
        Schema::create('defense_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defense_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Attempt 1');
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('attempt_type')->default('regular');
            $table->date('defense_date')->nullable();
            $table->time('defense_time')->nullable();
            $table->unsignedSmallInteger('defense_duration')->nullable();
            $table->string('defense_room')->nullable();
            $table->timestamp('paper_upload_deadline_at')->nullable();
            $table->timestamp('paper_upload_unlocked_until')->nullable();
            $table->timestamp('score_deadline_at')->nullable();
            $table->string('status')->default('setup');
            $table->decimal('final_score', 8, 2)->nullable();
            $table->decimal('final_score_override', 8, 2)->nullable();
            $table->text('final_score_override_reason')->nullable();
            $table->foreignId('final_score_override_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('results_released_at')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('reminder_1h_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['defense_period_id', 'team_id', 'attempt_number']);
            $table->index(['team_id', 'status']);
            $table->index(['defense_period_id', 'status']);
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->foreignId('defense_attempt_id')->nullable()->after('team_id')
                ->constrained('defense_attempts')
                ->nullOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('defense_attempt_id')->nullable()->after('paper_id')
                ->constrained('defense_attempts')
                ->nullOnDelete();
        });

        foreach (DB::table('teams')->orderBy('id')->get() as $team) {
            $periodId = DB::table('defense_periods')
                ->where('subject_id', $team->subject_id)
                ->where('type', 'final')
                ->value('id')
                ?? DB::table('defense_periods')
                    ->where('subject_id', $team->subject_id)
                    ->orderBy('sequence')
                    ->value('id');

            if (! $periodId) {
                continue;
            }

            $latestPaper = DB::table('papers')
                ->where('team_id', $team->id)
                ->latest('id')
                ->first();

            $status = $team->results_released_at
                ? 'published'
                : ($team->defense_date ? 'scheduled' : 'setup');

            $attemptId = DB::table('defense_attempts')->insertGetId([
                'defense_period_id' => $periodId,
                'team_id' => $team->id,
                'label' => 'Attempt 1',
                'attempt_number' => 1,
                'attempt_type' => 'regular',
                'defense_date' => $team->defense_date,
                'defense_time' => $team->defense_time,
                'defense_duration' => $team->defense_duration,
                'defense_room' => $team->defense_room,
                'score_deadline_at' => $team->score_deadline_at,
                'status' => $status,
                'final_score' => $latestPaper?->final_score,
                'final_score_override' => $latestPaper?->final_score_override,
                'final_score_override_reason' => $latestPaper?->final_score_override_reason,
                'final_score_override_by' => $latestPaper?->final_score_override_by,
                'results_released_at' => $team->results_released_at,
                'reminder_24h_sent_at' => $team->reminder_24h_sent_at,
                'reminder_1h_sent_at' => $team->reminder_1h_sent_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('papers')
                ->where('team_id', $team->id)
                ->whereNull('defense_attempt_id')
                ->update(['defense_attempt_id' => $attemptId]);
        }

        foreach (DB::table('reviews')->select(['id', 'paper_id'])->get() as $review) {
            $attemptId = DB::table('papers')
                ->where('id', $review->paper_id)
                ->value('defense_attempt_id');

            if ($attemptId) {
                DB::table('reviews')
                    ->where('id', $review->id)
                    ->update(['defense_attempt_id' => $attemptId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defense_attempt_id');
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defense_attempt_id');
        });

        Schema::dropIfExists('defense_attempts');
    }
};
