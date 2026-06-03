<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('reviews', 'auto_submitted_at')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->timestamp('auto_submitted_at')->nullable()->after('locked_at');
            });
        }

        if (! Schema::hasColumn('defense_attempt_reviewers', 'score_deadline_reminded_at')) {
            Schema::table('defense_attempt_reviewers', function (Blueprint $table) {
                $table->timestamp('score_deadline_reminded_at')->nullable()->after('removed_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('defense_attempt_reviewers', 'score_deadline_reminded_at')) {
            Schema::table('defense_attempt_reviewers', function (Blueprint $table) {
                $table->dropColumn('score_deadline_reminded_at');
            });
        }

        if (Schema::hasColumn('reviews', 'auto_submitted_at')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropColumn('auto_submitted_at');
            });
        }
    }
};
