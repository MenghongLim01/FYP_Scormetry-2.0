<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Google-Classroom-style flow: a document is first ATTACHED (draft) and then
     * TURNED IN. Judges/teacher only see it once turned in. turned_in_at records
     * when the student turned it in (null = attached draft).
     */
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->timestamp('turned_in_at')->nullable()->after('visibility_status');
        });

        // Existing papers are already submitted — treat them as turned in.
        DB::table('papers')
            ->whereIn('visibility_status', ['submitted', 'published'])
            ->update(['turned_in_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropColumn('turned_in_at');
        });
    }
};
