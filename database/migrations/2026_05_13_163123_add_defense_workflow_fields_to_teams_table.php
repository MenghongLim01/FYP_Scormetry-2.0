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
        Schema::table('teams', function (Blueprint $table) {
            $table->date('defense_date')->nullable()->after('name');
            $table->time('defense_time')->nullable()->after('defense_date');
            $table->string('defense_room')->nullable()->after('defense_time');
            $table->timestamp('score_deadline_at')->nullable()->after('defense_room');
            $table->timestamp('results_released_at')->nullable()->after('score_deadline_at');
            $table->timestamp('reminder_24h_sent_at')->nullable()->after('results_released_at');
            $table->timestamp('reminder_1h_sent_at')->nullable()->after('reminder_24h_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'defense_date',
                'defense_time',
                'defense_room',
                'score_deadline_at',
                'results_released_at',
                'reminder_24h_sent_at',
                'reminder_1h_sent_at',
            ]);
        });
    }
};
