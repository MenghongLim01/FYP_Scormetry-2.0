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
        if (! Schema::hasColumn('subject_invitations', 'role_label')) {
            Schema::table('subject_invitations', function (Blueprint $table) {
                $table->string('role_label')->nullable()->after('committee_role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_invitations', function (Blueprint $table) {
            $table->dropColumn('role_label');
        });
    }
};
