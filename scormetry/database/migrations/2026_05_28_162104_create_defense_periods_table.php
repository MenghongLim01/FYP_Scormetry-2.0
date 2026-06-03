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
        Schema::create('defense_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('score_scale')->default('points_100');
            $table->decimal('passing_score', 6, 2)->default(50);
            $table->string('status')->default('setup');
            $table->timestamps();

            $table->unique(['subject_id', 'type']);
            $table->index(['subject_id', 'sequence']);
        });

        Schema::table('rubrics', function (Blueprint $table) {
            $table->foreignId('defense_period_id')->nullable()->after('subject_id')
                ->constrained('defense_periods')
                ->nullOnDelete();
        });

        foreach (DB::table('subjects')->orderBy('id')->get(['id', 'passing_score']) as $subject) {
            $now = now();

            DB::table('defense_periods')->insert([
                'subject_id' => $subject->id,
                'name' => 'Midterm Defense',
                'type' => 'midterm',
                'sequence' => 1,
                'score_scale' => 'points_100',
                'passing_score' => $subject->passing_score ?: 50,
                'status' => 'setup',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $finalPeriodId = DB::table('defense_periods')->insertGetId([
                'subject_id' => $subject->id,
                'name' => 'Final Defense',
                'type' => 'final',
                'sequence' => 2,
                'score_scale' => 'points_100',
                'passing_score' => $subject->passing_score ?: 50,
                'status' => 'setup',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('rubrics')
                ->where('subject_id', $subject->id)
                ->whereNull('defense_period_id')
                ->update(['defense_period_id' => $finalPeriodId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rubrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('defense_period_id');
        });

        Schema::dropIfExists('defense_periods');
    }
};
