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
            // The team's advisor (supervisor). Set by the students themselves or
            // the FYP instructor. This does NOT make the advisor a judge — it is
            // purely the team's listed advisor until the instructor invites them.
            $table->foreignId('advisor_id')->nullable()->after('subject_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('advisor_id');
        });
    }
};
