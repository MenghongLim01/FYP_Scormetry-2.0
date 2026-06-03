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
        Schema::table('subject_members', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'blocked'])->default('pending')->after('role');
            $table->string('role_label')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_members', function (Blueprint $table) {
            $table->dropColumn(['status', 'role_label']);
        });
    }
};
